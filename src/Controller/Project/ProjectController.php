<?php

declare(strict_types=1);

namespace App\Controller\Project;

use App\Controller\Trait\CurrentUserTrait;
use App\Dto\Project\CreateProjectRequest;
use App\Dto\Project\ManageProjectMembersRequest;
use App\Dto\Project\ProjectResponse;
use App\Dto\Project\SearchProjectRequest;
use App\Dto\Project\UpdateProjectRequest;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Http\ApiErrorResponse;
use App\Http\RequestPayloadParser;
use App\Repository\ProjectRepositoryInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
#[OA\Tag(name: 'Projets')]
class ProjectController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly RequestPayloadParser $requestPayloadParser,
    ) {
    }

    #[Route('/projects', name: 'project_list', methods: ['GET'])]
    #[OA\Get(
        path: '/projects',
        summary: 'Liste des projets accessibles',
        responses: [
            new OA\Response(response: 200, description: 'Liste de projets'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function list(ProjectRepositoryInterface $projectRepository): JsonResponse
    {
        $user = $this->getCurrentUser();

        $projects = $projectRepository->findAccessibleByUser($user);

        $data = array_map(
            fn (Project $project): array => ProjectResponse::fromProject($project)->toArray(),
            $projects,
        );

        return new JsonResponse($data);
    }

    #[Route('/project/{id}', name: 'project_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/project/{id}',
        summary: 'Détail d\'un projet',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail du projet'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Projet introuvable'),
        ],
    )]
    public function detail(
        int $id,
        ProjectRepositoryInterface $projectRepository,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $project = $projectRepository->findById($id);
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        if (!$project->isAccessibleBy($user)) {
            throw $this->createAccessDeniedException();
        }

        return new JsonResponse(ProjectResponse::fromProject($project)->toArray());
    }

    #[Route('/project', name: 'project_create', methods: ['POST'])]
    #[OA\Post(
        path: '/project',
        summary: 'Créer un projet',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Mon projet'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'startAt', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'endAt', type: 'string', format: 'date', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Projet créé'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $owner = $this->getCurrentUser();

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(CreateProjectRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var CreateProjectRequest $dto */
        $project = new Project();
        $project
            ->setTitle((string) $dto->title)
            ->setDescription($dto->description)
            ->setStatus(ProjectStatus::IN_PROGRESS)
            ->setOwner($owner)
            ->setStartAt($dto->getStartAtAsDateTime())
            ->setEndAt($dto->getEndAtAsDateTime());

        $error = $this->requestPayloadParser->validateEntity($project);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->persist($project);
        $entityManager->flush();

        return new JsonResponse(
            ProjectResponse::fromProject($project)->toArray(),
            Response::HTTP_CREATED
        );
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     */
    #[Route('/project/{id}', name: 'project_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        path: '/project/{id}',
        summary: 'Modifier un projet (owner ou manager)',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'startAt', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'endAt', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['en cours', 'terminé', 'annulé'], nullable: true),
                ],
            ),
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Projet mis à jour'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Projet introuvable'),
        ],
    )]
    public function update(
        int $id,
        Request $request,
        ProjectRepositoryInterface $projectRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $project = $projectRepository->findById($id);
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(UpdateProjectRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var UpdateProjectRequest $dto */
        if ($project->isRestoreRequested($dto->status)) {
            if (!$project->canBeRestoredBy($user)) {
                throw $this->createAccessDeniedException();
            }

            $project->restore();

            $error = $this->requestPayloadParser->validateEntity($project);
            if ($error instanceof JsonResponse) {
                return $error;
            }

            $entityManager->flush();

            return new JsonResponse(ProjectResponse::fromProject($project)->toArray());
        }

        if (!$project->canBeModifiedBy($user)) {
            throw $this->createAccessDeniedException();
        }

        /** @var UpdateProjectRequest $dto */
        if (\is_string($dto->title)) {
            $project->setTitle($dto->title);
        }

        if (null !== $dto->description || array_key_exists('description', $data)) {
            $project->setDescription($dto->description);
        }

        if (null !== $dto->startAt || array_key_exists('startAt', $data)) {
            $project->setStartAt($dto->getStartAtAsDateTime());
        }

        if (null !== $dto->endAt || array_key_exists('endAt', $data)) {
            $project->setEndAt($dto->getEndAtAsDateTime());
        }

        if (\is_string($dto->status)) {
            $newStatus = ProjectStatus::tryFrom($dto->status);
            if (null === $newStatus) {
                return ApiErrorResponse::badRequest('Statut de projet invalide.');
            }

            $project->changeStatus($newStatus);
        }

        $error = $this->requestPayloadParser->validateEntity($project);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->flush();

        return new JsonResponse(ProjectResponse::fromProject($project)->toArray());
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    #[Route('/project/{id}/members', name: 'project_members_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/project/{id}/members',
        summary: 'Ajouter des membres au projet',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['members'],
                properties: [
                    new OA\Property(
                        property: 'members',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [2, 3],
                    ),
                ],
            ),
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Membres ajoutés'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Projet ou utilisateur introuvable'),
            new OA\Response(response: 409, description: 'Membre invalide'),
        ],
    )]
    public function addMembers(
        int $id,
        Request $request,
        ProjectRepositoryInterface $projectRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $project = $projectRepository->findById($id);
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        if (!$project->canManageMembersBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(ManageProjectMembersRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var ManageProjectMembersRequest $dto */
        $memberIds = array_unique($dto->members ?? []);
        foreach ($memberIds as $memberId) {
            $member = $userRepository->find($memberId);
            if (!$member instanceof User) {
                return ApiErrorResponse::notFound('Utilisateur introuvable.');
            }

            if (!$project->canBeAddedAsMember($member)) {
                return ApiErrorResponse::conflict(
                    'INVALID_MEMBER',
                    'Le propriétaire ne peut pas être ajouté comme membre.',
                );
            }

            $project->addMember($member);
        }

        $error = $this->requestPayloadParser->validateEntity($project);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->flush();

        return new JsonResponse(ProjectResponse::fromProject($project)->toArray());
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    #[Route('/project/{id}/members', name: 'project_members_remove', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/project/{id}/members',
        summary: 'Retirer des membres du projet',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['members'],
                properties: [
                    new OA\Property(
                        property: 'members',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [2],
                    ),
                ],
            ),
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Membres retirés'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Projet ou membre introuvable'),
        ],
    )]
    public function removeMembers(
        int $id,
        Request $request,
        ProjectRepositoryInterface $projectRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $project = $projectRepository->findById($id);
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        if (!$project->canManageMembersBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(ManageProjectMembersRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var ManageProjectMembersRequest $dto */
        $memberIds = array_unique($dto->members ?? []);
        foreach ($memberIds as $memberId) {
            $member = $userRepository->find($memberId);
            if (!$member instanceof User) {
                return ApiErrorResponse::notFound('Utilisateur introuvable.');
            }

            if (!$project->isOneOfMembers($member)) {
                return ApiErrorResponse::notFound('Cet utilisateur n\'est pas membre du projet.');
            }

            $project->removeMember($member);
        }

        $error = $this->requestPayloadParser->validateEntity($project);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->flush();

        return new JsonResponse(ProjectResponse::fromProject($project)->toArray());
    }

    #[Route('/projects/search', name: 'project_search', methods: ['POST'])]
    #[OA\Post(
        path: '/projects/search',
        summary: 'Rechercher / filtrer les projets accessibles',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'filters',
                        properties: [
                            new OA\Property(
                                property: 'status',
                                type: 'array',
                                items: new OA\Items(type: 'string', enum: ['en cours', 'terminé', 'annulé']),
                            ),
                            new OA\Property(property: 'archived', type: 'boolean', nullable: true),
                        ],
                        type: 'object',
                    ),
                    new OA\Property(
                        property: 'sort',
                        properties: [
                            new OA\Property(property: 'field', type: 'string', example: 'createdAt'),
                            new OA\Property(property: 'order', type: 'string', enum: ['asc', 'desc']),
                        ],
                        type: 'object',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Liste de projets filtrés'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function search(
        Request $request,
        ProjectRepositoryInterface $projectRepository,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(SearchProjectRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var SearchProjectRequest $dto */
        $projects = $projectRepository->searchByUser($user, $dto);

        $payload = array_map(
            static fn (Project $project): array => ProjectResponse::fromProject($project)->toArray(),
            $projects,
        );

        return new JsonResponse($payload);
    }
}
