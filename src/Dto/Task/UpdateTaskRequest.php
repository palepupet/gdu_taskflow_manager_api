<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Enum\TaskPriority;
use App\Enum\TaskState;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateTaskRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le titre ne doit pas être vide.', allowNull: true)]
        #[Assert\Length(max: 100)]
        public ?string $title = null,
        public ?string $description = null,
        #[Assert\DateTime(message: 'La date d\'échéance n\'est pas valide.')]
        public ?string $dueAt = null,
        #[Assert\Choice(callback: [TaskPriority::class, 'values'])]
        public ?string $priority = null,
        #[Assert\Choice(callback: [TaskState::class, 'values'])]
        public ?string $state = null,
        public ?int $assignee = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $assignee = null;
        if (array_key_exists('assignee', $data) && is_numeric($data['assignee'])) {
            $assignee = (int) $data['assignee'];
        }

        return new self(
            title: isset($data['title']) && \is_string($data['title']) ? $data['title'] : null,
            description: array_key_exists('description', $data)
                ? (\is_string($data['description']) ? $data['description'] : null)
                : null,
            dueAt: array_key_exists('dueAt', $data)
                ? (\is_string($data['dueAt']) ? $data['dueAt'] : null)
                : null,
            priority: isset($data['priority']) && \is_string($data['priority']) ? $data['priority'] : null,
            state: isset($data['state']) && \is_string($data['state']) ? $data['state'] : null,
            assignee: $assignee,
        );
    }

    public function getDueAtAsDateTime(): ?\DateTimeImmutable
    {
        return null !== $this->dueAt
            ? new \DateTimeImmutable($this->dueAt)
            : null;
    }

    public function getPriorityAsEnum(): ?TaskPriority
    {
        if (null === $this->priority) {
            return null;
        }

        return TaskPriority::from($this->priority);
    }

    public function getStateAsEnum(): ?TaskState
    {
        if (null === $this->state) {
            return null;
        }

        return TaskState::from($this->state);
    }
}
