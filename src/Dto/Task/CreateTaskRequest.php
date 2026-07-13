<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Enum\TaskPriority;
use Symfony\Component\Validator\Constraints as Assert;

class CreateTaskRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
        #[Assert\Length(max: 100)]
        public ?string $title = null,
        public ?string $description = null,
        #[Assert\Date(message: 'La date d\'échéance n\'est pas valide.')]
        public ?string $dueAt = null,
        #[Assert\Choice(callback: [TaskPriority::class, 'values'])]
        public ?string $priority = null,
        public ?int $assignee = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $assignee = null;
        if (isset($data['assignee']) && is_numeric($data['assignee'])) {
            $assignee = (int) $data['assignee'];
        }

        return new self(
            title: isset($data['title']) && \is_string($data['title']) ? $data['title'] : null,
            description: isset($data['description']) && \is_string($data['description']) ? $data['description'] : null,
            dueAt: isset($data['dueAt']) && \is_string($data['dueAt']) ? $data['dueAt'] : null,
            priority: isset($data['priority']) && \is_string($data['priority']) ? $data['priority'] : null,
            assignee: $assignee,
        );
    }

    public function getDueAtAsDateTime(): ?\DateTimeImmutable
    {
        return null !== $this->dueAt
            ? new \DateTimeImmutable($this->dueAt)
            : null;
    }

    public function getPriorityAsEnum(): TaskPriority
    {
        if (null === $this->priority) {
            return TaskPriority::MEDIUM;
        }

        return TaskPriority::from($this->priority);
    }
}
