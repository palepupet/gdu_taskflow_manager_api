<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Dto\User\UserSummaryResponse;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;

class TaskResponse
{
    /**
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public ?string $dueAt,
        public string $priority,
        public string $state,
        public int $projectId,
        public ?UserSummaryResponse $assignee,
        public string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    public static function fromTask(Task $task): self
    {
        $id = $task->getId();
        $project = $task->getProject();
        $createdAt = $task->getCreatedAt();

        if (null === $id || !$project instanceof Project || null === $createdAt) {
            throw new \LogicException('Une tâche persistée doit avoir un id, un projet et une date de création.');
        }

        $projectId = $project->getId();
        if (null === $projectId) {
            throw new \LogicException('Le projet parent doit avoir un identifiant.');
        }

        $assignee = $task->getAssignee();
        $assigneeResponse = $assignee instanceof User
            ? UserSummaryResponse::fromUser($assignee)
            : null;

        return new self(
            id: $id,
            title: (string) $task->getTitle(),
            description: $task->getDescription(),
            dueAt: $task->getDueAt()?->format(\DateTimeInterface::ATOM),
            priority: $task->getPriority()->value,
            state: $task->getState()->value,
            projectId: $projectId,
            assignee: $assigneeResponse,
            createdAt: $createdAt->format(\DateTimeInterface::ATOM),
            updatedAt: $task->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'dueAt' => $this->dueAt,
            'priority' => $this->priority,
            'state' => $this->state,
            'projectId' => $this->projectId,
            'assignee' => $this->assignee?->toArray(),
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
