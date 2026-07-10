<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Enum\TaskPriority;
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
        public ?int $assigneeId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $assigneeId = null;
        if (array_key_exists('assigneeId', $data) && is_numeric($data['assigneeId'])) {
            $assigneeId = (int) $data['assigneeId'];
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
            assigneeId: $assigneeId,
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
}
