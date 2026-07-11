<?php

declare(strict_types=1);

namespace App\Controller\Task;

use App\Controller\Trait\CurrentUserTrait;
use App\Dto\Task\CreateTaskRequest;
use App\Dto\Task\TaskResponse;
use App\Dto\Task\UpdateTaskRequest;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskState;
use App\Http\ApiErrorResponse;
use App\Http\RequestPayloadParser;
use App\Repository\ProjectRepositoryInterface;
use App\Repository\TaskRepositoryInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class TaskController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly RequestPayloadParser $requestPayloadParser,
    ) {
    }

    #[Route('/project/{id}/tasks', name:'task_list', requirements: ['id' => '\d+'], methods: ['GET'])]
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

    #[Route('/task/{id}', name:'task_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
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
    #[Route('/task/{id}', name:'task_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function update(
        int $id,
        Request $request,
        TaskRepositoryInterface $taskRepository,
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
            || array_key_exists('assignee', $data);

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
    #[Route('/project/{id}/tasks', name:'task_create', requirements: ['id' => '\d+'], methods: ['POST'])]
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
}
