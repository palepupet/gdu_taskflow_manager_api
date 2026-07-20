<?php

declare(strict_types=1);

namespace App\Controller\Tag;

use App\Controller\Trait\CurrentUserTrait;
use App\Dto\Tag\CreateTagRequest;
use App\Dto\Tag\TagResponse;
use App\Dto\Tag\UpdateTagRequest;
use App\Entity\Project;
use App\Entity\Tag;
use App\Http\ApiErrorResponse;
use App\Http\RequestPayloadParser;
use App\Repository\ProjectRepositoryInterface;
use App\Repository\TagRepositoryInterface;
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
#[OA\Tag(name: 'Tags')]
class TagController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly RequestPayloadParser $requestPayloadParser,
    ) {
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    #[Route('/project/{id}/tags', name: 'tag_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/project/{id}/tags',
        summary: 'Créer un tag sur un projet',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['label'],
                properties: [
                    new OA\Property(property: 'label', type: 'string', example: 'urgent'),
                ],
            ),
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Tag créé'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Projet introuvable'),
            new OA\Response(response: 409, description: 'Libellé déjà utilisé'),
        ],
    )]
    public function create(
        int $id,
        Request $request,
        ProjectRepositoryInterface $projectRepository,
        TagRepositoryInterface $tagRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $project = $projectRepository->findById($id);
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        if (!$project->canCreateTagBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(CreateTagRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var CreateTagRequest $dto */
        $label = trim((string) $dto->label);
        if ($tagRepository->isTagAlreadyExistsWithThisLabel($label, $id)) {
            return ApiErrorResponse::conflict('TAG_ALREADY_EXISTS', 'Ce libellé de tag existe déjà pour ce projet.');
        }

        $tag = new Tag();
        $tag->setLabel($label);
        $project->addTag($tag);

        $error = $this->requestPayloadParser->validateEntity($tag);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->persist($tag);
        $entityManager->flush();

        return new JsonResponse(
            TagResponse::fromTag($tag)->toArray(),
            Response::HTTP_CREATED,
        );
    }

    #[Route('/project/{id}/tags', name: 'tag_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/project/{id}/tags',
        summary: 'Lister les tags d\'un projet',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des tags'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Projet introuvable'),
        ],
    )]
    public function list(
        int $id,
        ProjectRepositoryInterface $projectRepository,
        TagRepositoryInterface $tagRepository,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $project = $projectRepository->findById($id);
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        if (!$project->isAccessibleBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $tags = $tagRepository->findByProjectId($id);
        $data = array_map(
            fn (Tag $tag): array => TagResponse::fromTag($tag)->toArray(),
            $tags,
        );

        return new JsonResponse($data);
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     */
    #[Route('/tag/{id}', name: 'tag_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        path: '/tag/{id}',
        summary: 'Renommer un tag',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['label'],
                properties: [
                    new OA\Property(property: 'label', type: 'string', example: 'prioritaire'),
                ],
            ),
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tag mis à jour'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Tag introuvable'),
            new OA\Response(response: 409, description: 'Libellé déjà utilisé'),
        ],
    )]
    public function update(
        int $id,
        Request $request,
        TagRepositoryInterface $tagRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $tag = $tagRepository->findById($id);
        if (!$tag instanceof Tag) {
            return ApiErrorResponse::notFound('Tag introuvable.');
        }

        $project = $tag->getProject();
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        if (!$project->canUpdateTagBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(UpdateTagRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var UpdateTagRequest $dto */
        $label = trim((string) $dto->label);
        $projectId = $project->getId();
        if (null === $projectId) {
            throw new \LogicException('Le projet doit avoir un id.');
        }

        $tagId = $tag->getId();
        if (null === $tagId) {
            throw new \LogicException('Le tag doit avoir un id.');
        }

        if ($tagRepository->isTagAlreadyExistsWithThisLabel($label, $projectId, $tagId)) {
            return ApiErrorResponse::conflict(
                'TAG_ALREADY_EXISTS',
                'Ce libellé de tag existe déjà pour ce projet.',
            );
        }

        $tag->setLabel($label);

        $error = $this->requestPayloadParser->validateEntity($tag);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->flush();

        return new JsonResponse(TagResponse::fromTag($tag)->toArray());
    }

    #[Route('/tag/{id}', name: 'tag_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/tag/{id}',
        summary: 'Supprimer un tag',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Tag supprimé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Tag introuvable'),
        ],
    )]
    public function delete(
        int $id,
        TagRepositoryInterface $tagRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $tag = $tagRepository->findById($id);
        if (!$tag instanceof Tag) {
            return ApiErrorResponse::notFound('Tag introuvable.');
        }

        $project = $tag->getProject();
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        if (!$project->canDeleteTagBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($tag);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
