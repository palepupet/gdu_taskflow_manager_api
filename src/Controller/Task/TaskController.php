<?php

declare(strict_types=1);

namespace App\Controller\Task;

use App\Controller\Trait\CurrentUserTrait;
use App\Dto\Task\CreateTaskRequest;
use App\Dto\Task\SearchTaskRequest;
use App\Dto\Task\TaskResponse;
use App\Dto\Task\UpdateTaskRequest;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskState;
use App\Http\ApiErrorResponse;
use App\Http\RequestPayloadParser;
use App\Repository\ProjectRepositoryInterface;
use App\Repository\TagRepositoryInterface;
use App\Repository\TaskRepositoryInterface;
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
 * @SuppressWarnings("PHPMD.ExcessiveClassComplexity")
 */
#[OA\Tag(name: 'Tâches')]
class TaskController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly RequestPayloadParser $requestPayloadParser,
    ) {
    }

    #[Route('/project/{id}/tasks', name: 'task_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/project/{id}/tasks',
        summary: 'Lister les tâches d\'un projet',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des tâches'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Projet introuvable'),
        ],
    )]
    public function list(
        int $id,
        ProjectRepositoryInterface $projectRepository,
        TaskRepositoryInterface $taskRepository,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $project = $projectRepository->findById($id);
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        if (!$project->isAccessibleBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $tasks = $taskRepository->findByProjectId($id);
        $data = array_map(
            fn (Task $task): array => TaskResponse::fromTask($task)->toArray(),
            $tasks,
        );

        return new JsonResponse($data);
    }

    #[Route('/task/{id}', name: 'task_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        path: '/task/{id}',
        summary: 'Détail d\'une tâche',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail de la tâche'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Tâche introuvable'),
        ],
    )]
    public function detail(
        int $id,
        TaskRepositoryInterface $taskRepository,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $task = $taskRepository->findById($id);
        if (!$task instanceof Task) {
            return ApiErrorResponse::notFound('Tâche introuvable.');
        }

        if (!$task->isAccessibleBy($user)) {
            throw $this->createAccessDeniedException();
        }

        return new JsonResponse(TaskResponse::fromTask($task)->toArray());
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     */
    #[Route('/task/{id}', name: 'task_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        path: '/task/{id}',
        summary: 'Modifier une tâche',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'dueAt', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'priority', type: 'string', enum: ['basse', 'moyenne', 'élevée'], nullable: true),
                    new OA\Property(property: 'state', type: 'string', enum: ['ouvert', 'en cours', 'terminé'], nullable: true),
                    new OA\Property(property: 'assignee', type: 'integer', nullable: true),
                    new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'integer'), nullable: true),
                ],
            ),
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tâche mise à jour'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Tâche introuvable'),
        ],
    )]
    public function update(
        int $id,
        Request $request,
        TaskRepositoryInterface $taskRepository,
        TagRepositoryInterface $tagRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $task = $taskRepository->findById($id);
        if (!$task instanceof Task) {
            return ApiErrorResponse::notFound('Tâche introuvable.');
        }

        if (!$task->isAccessibleBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(UpdateTaskRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var UpdateTaskRequest $dto */
        $shouldUpdateNonStateField = \is_string($dto->title)
            || array_key_exists('description', $data)
            || array_key_exists('dueAt', $data)
            || null !== $dto->getPriorityAsEnum()
            || array_key_exists('assignee', $data)
            || array_key_exists('tags', $data);

        if ($shouldUpdateNonStateField && !$task->canBeModifiedBy($user)) {
            throw $this->createAccessDeniedException();
        }

        if (\is_string($dto->title)) {
            $task->setTitle($dto->title);
        }

        if (null !== $dto->description || array_key_exists('description', $data)) {
            $task->setDescription($dto->description);
        }

        if (null !== $dto->dueAt || array_key_exists('dueAt', $data)) {
            $task->setDueAt($dto->getDueAtAsDateTime());
        }

        $priority = $dto->getPriorityAsEnum();
        if (null !== $priority) {
            $task->setPriority($priority);
        }

        $newState = $dto->getStateAsEnum();
        if (null !== $newState && array_key_exists('state', $data)) {
            if (!$task->canTransitionTo($newState)) {
                return ApiErrorResponse::badRequest('Transition d\'état invalide.');
            }

            if (!$task->canChangeStateBy($user, $newState)) {
                throw $this->createAccessDeniedException();
            }

            $task->setState($newState);
        }

        if (array_key_exists('assignee', $data) && null === $data['assignee']) {
            $task->setAssignee(null);
        }

        if (array_key_exists('assignee', $data) && null !== $data['assignee']) {
            $assignee = $userRepository->find($dto->assignee);
            if (!$assignee instanceof User) {
                return ApiErrorResponse::notFound('Utilisateur assigné introuvable.');
            }

            $project = $task->getProject();
            if ($project instanceof Project) {
                $project->addMemberWhenAssigning($assignee);
            }

            $task->setAssignee($assignee);
        }

        if (array_key_exists('tags', $data)) {
            if (!\is_array($data['tags'])) {
                return ApiErrorResponse::badRequest('tags doit être un tableau d\'id.');
            }

            $project = $task->getProject();
            if (!$project instanceof Project) {
                return ApiErrorResponse::notFound('Projet introuvable.');
            }

            $projectId = $project->getId();
            if (null === $projectId) {
                throw new \LogicException('Le projet doit avoir un id.');
            }

            foreach ($task->getTags()->toArray() as $existingTag) {
                $task->removeTag($existingTag);
            }

            /** @var list<int> $tagIds */
            $tagIds = $dto->tags ?? [];

            foreach ($tagIds as $tagId) {
                $tag = $tagRepository->findById($tagId);
                if (!$tag instanceof Tag) {
                    return ApiErrorResponse::notFound('Tag introuvable.');
                }

                $tagProject = $tag->getProject();
                if (
                    !$tagProject instanceof Project
                    || $tagProject->getId() !== $projectId
                ) {
                    return ApiErrorResponse::conflict(
                        'TAG_NOT_IN_PROJECT',
                        'Ce tag n\'appartient pas au projet de la tâche.',
                    );
                }

                $task->addTag($tag);
            }
        }

        $error = $this->requestPayloadParser->validateEntity($task);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->flush();

        return new JsonResponse(TaskResponse::fromTask($task)->toArray());
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    #[Route('/project/{id}/tasks', name: 'task_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/project/{id}/tasks',
        summary: 'Créer une tâche',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Ma tâche'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'dueAt', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'priority', type: 'string', enum: ['basse', 'moyenne', 'élevée'], nullable: true),
                    new OA\Property(property: 'assignee', type: 'integer', nullable: true),
                ],
            ),
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Tâche créée'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Projet introuvable'),
        ],
    )]
    public function create(
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

        if (!$project->canCreateTaskBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(CreateTaskRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var CreateTaskRequest $dto */
        $task = new Task();
        $task->setTitle((string) $dto->title)
            ->setDescription($dto->description)
            ->setDueAt($dto->getDueAtAsDateTime())
            ->setPriority($dto->getPriorityAsEnum())
            ->setState(TaskState::OPEN);

        if (null !== $dto->assignee) {
            $assignee = $userRepository->find($dto->assignee);
            if (!$assignee instanceof User) {
                return ApiErrorResponse::notFound('Utilisateur assigné introuvable.');
            }

            $project->addMemberWhenAssigning($assignee);
            $task->setAssignee($assignee);
        }

        $project->addTask($task);

        $error = $this->requestPayloadParser->validateEntity($task);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->persist($task);
        $entityManager->flush();

        return new JsonResponse(TaskResponse::fromTask($task)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/task/{id}', name: 'task_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/task/{id}',
        summary: 'Supprimer une tâche',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Tâche supprimée'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Tâche introuvable'),
        ],
    )]
    public function delete(
        int $id,
        TaskRepositoryInterface $taskRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $task = $taskRepository->findById($id);
        if (!$task instanceof Task) {
            return ApiErrorResponse::notFound('Tâche introuvable.');
        }

        if (!$task->canBeModifiedBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $entityManager->remove($task);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/task/{id}/tags/{tagId}', name: 'task_tag_add', requirements: ['id' => '\d+', 'tagId' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/task/{id}/tags/{tagId}',
        summary: 'Associer un tag à une tâche',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tagId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tag associé'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Tâche ou tag introuvable'),
            new OA\Response(response: 409, description: 'Tag hors projet ou déjà lié'),
        ],
    )]
    public function addTag(
        int $id,
        int $tagId,
        TaskRepositoryInterface $taskRepository,
        TagRepositoryInterface $tagRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $task = $taskRepository->findById($id);
        if (!$task instanceof Task) {
            return ApiErrorResponse::notFound('Tâche introuvable.');
        }

        if (!$task->canBeModifiedBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $tag = $tagRepository->findById($tagId);
        if (!$tag instanceof Tag) {
            return ApiErrorResponse::notFound('Tag introuvable.');
        }

        $taskProject = $task->getProject();
        $tagProject = $tag->getProject();
        if (
            !$taskProject instanceof Project
            || !$tagProject instanceof Project
            || $taskProject->getId() !== $tagProject->getId()
        ) {
            return ApiErrorResponse::conflict(
                'TAG_NOT_IN_PROJECT',
                'Ce tag n\'appartient pas au projet de la tâche.',
            );
        }

        if ($task->hasTag($tag)) {
            return ApiErrorResponse::conflict(
                'TAG_ALREADY_LINKED',
                'Ce tag est déjà associé à la tâche.',
            );
        }

        $task->addTag($tag);
        $entityManager->flush();

        return new JsonResponse(TaskResponse::fromTask($task)->toArray());
    }

    #[Route('/task/{id}/tags/{tagId}', name: 'task_tag_remove', requirements: ['id' => '\d+', 'tagId' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        path: '/task/{id}/tags/{tagId}',
        summary: 'Retirer un tag d\'une tâche',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'tagId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tag retiré'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Tâche, tag ou liaison introuvable'),
            new OA\Response(response: 409, description: 'Tag hors projet'),
        ],
    )]
    public function removeTag(
        int $id,
        int $tagId,
        TaskRepositoryInterface $taskRepository,
        TagRepositoryInterface $tagRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $task = $taskRepository->findById($id);
        if (!$task instanceof Task) {
            return ApiErrorResponse::notFound('Tâche introuvable.');
        }

        if (!$task->canBeModifiedBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $tag = $tagRepository->findById($tagId);
        if (!$tag instanceof Tag) {
            return ApiErrorResponse::notFound('Tag introuvable.');
        }

        $taskProject = $task->getProject();
        $tagProject = $tag->getProject();
        if (
            !$taskProject instanceof Project
            || !$tagProject instanceof Project
            || $taskProject->getId() !== $tagProject->getId()
        ) {
            return ApiErrorResponse::conflict(
                'TAG_NOT_IN_PROJECT',
                'Ce tag n\'appartient pas au projet de la tâche.',
            );
        }

        if (!$task->hasTag($tag)) {
            return ApiErrorResponse::notFound('Ce tag n\'est pas associé à la tâche.');
        }

        $task->removeTag($tag);

        $entityManager->flush();

        return new JsonResponse(TaskResponse::fromTask($task)->toArray());
    }

    #[Route('/project/{id}/tasks/search', name: 'task_search', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        path: '/project/{id}/tasks/search',
        summary: 'Rechercher / filtrer les tâches d\'un projet',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'filters',
                        properties: [
                            new OA\Property(property: 'state', type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'priority', type: 'array', items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'dueBefore', type: 'string', format: 'date', nullable: true),
                            new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'integer')),
                            new OA\Property(property: 'assignee', type: 'integer', nullable: true),
                        ],
                        type: 'object',
                    ),
                    new OA\Property(
                        property: 'sort',
                        properties: [
                            new OA\Property(property: 'field', type: 'string', example: 'dueAt'),
                            new OA\Property(property: 'order', type: 'string', enum: ['asc', 'desc']),
                        ],
                        type: 'object',
                    ),
                ],
            ),
        ),
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste de tâches filtrées'),
            new OA\Response(response: 400, description: 'Requête invalide'),
            new OA\Response(response: 403, description: 'Accès refusé'),
            new OA\Response(response: 404, description: 'Projet introuvable'),
        ],
    )]
    public function search(
        int $id,
        Request $request,
        ProjectRepositoryInterface $projectRepository,
        TaskRepositoryInterface $taskRepository,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $project = $projectRepository->findById($id);
        if (!$project instanceof Project) {
            return ApiErrorResponse::notFound('Projet introuvable.');
        }

        if (!$project->isAccessibleBy($user)) {
            throw $this->createAccessDeniedException();
        }

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(SearchTaskRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var SearchTaskRequest $dto */
        $tasks = $taskRepository->searchByProjectId($id, $dto);
        $payload = array_map(
            static fn (Task $task): array => TaskResponse::fromTask($task)->toArray(),
            $tasks,
        );

        return new JsonResponse($payload);
    }
}
