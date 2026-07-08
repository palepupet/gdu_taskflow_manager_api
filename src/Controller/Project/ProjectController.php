<?php

declare(strict_types=1);

namespace App\Controller\Project;

use App\Controller\Trait\CurrentUserTrait;
use App\Dto\Project\CreateProjectRequest;
use App\Dto\Project\ProjectResponse;
use App\Dto\Project\UpdateProjectRequest;
use App\Entity\Project;
use App\Enum\ProjectStatus;
use App\Http\ApiErrorResponse;
use App\Http\RequestPayloadParser;
use App\Repository\ProjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly RequestPayloadParser $requestPayloadParser,
    ) {
    }

    #[Route('/projects', name:'project_list', methods: ['GET'])]
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

    #[Route('/project/{id}', name:'project_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
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

    #[Route('/project', name:'project_create', methods: ['POST'])]
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
    #[Route('/project/{id}', name:'project_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
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
}
