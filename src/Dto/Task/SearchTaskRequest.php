<?php

declare(strict_types=1);

namespace App\Dto\Task;

use App\Enum\TaskPriority;
use App\Enum\TaskState;
use Symfony\Component\Validator\Constraints as Assert;

class SearchTaskRequest
{
    /**
     * @param list<string>|null $state
     * @param list<string>|null $priority
     * @param list<int>|null    $tags
     */
    public function __construct(
        #[Assert\All([new Assert\Choice(callback: [TaskState::class, 'values'])])]
        public ?array $state = null,
        #[Assert\All([new Assert\Choice(callback: [TaskPriority::class, 'values'])])]
        public ?array $priority = null,
        #[Assert\Date(message: 'La date dueBefore n\'est pas valide.')]
        public ?string $dueBefore = null,
        #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
        public ?array $tags = null,
        public ?int $assignee = null,
        #[Assert\Choice(choices: ['id', 'dueAt', 'priority', 'state', 'createdAt', 'title'])]
        public ?string $sortField = null,
        #[Assert\Choice(choices: ['asc', 'desc'])]
        public ?string $sortOrder = null,
    ) {
    }

    /**
     * @SuppressWarnings("PHPMD.NPathComplexity")
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $filters = [];
        if (isset($data['filters']) && \is_array($data['filters'])) {
            $filters = $data['filters'];
        }

        $sort = [];
        if (isset($data['sort']) && \is_array($data['sort'])) {
            $sort = $data['sort'];
        }

        $state = null;
        if (isset($filters['state'])) {
            $state = \is_array($filters['state'])
                ? array_values(array_filter($filters['state'], '\is_string'))
                : (\is_string($filters['state']) ? [$filters['state']] : null);
        }

        $priority = null;
        if (isset($filters['priority'])) {
            $priority = \is_array($filters['priority'])
                ? array_values(array_filter($filters['priority'], '\is_string'))
                : (\is_string($filters['priority']) ? [$filters['priority']] : null);
        }

        $tags = null;
        if (isset($filters['tags']) && \is_array($filters['tags'])) {
            $tags = array_values(array_unique(array_filter(
                array_map(
                    static fn (mixed $id): ?int => is_numeric($id) ? (int) $id : null,
                    $filters['tags'],
                ),
                static fn (?int $id): bool => null !== $id,
            )));
        }

        $assignee = null;
        if (isset($filters['assignee']) && is_numeric($filters['assignee'])) {
            $assignee = (int) $filters['assignee'];
        }

        return new self(
            state: $state,
            priority: $priority,
            dueBefore: isset($filters['dueBefore']) && \is_string($filters['dueBefore'])
                ? $filters['dueBefore']
                : null,
            tags: $tags,
            assignee: $assignee,
            sortField: isset($sort['field']) && \is_string($sort['field']) ? $sort['field'] : null,
            sortOrder: isset($sort['order']) && \is_string($sort['order']) ? $sort['order'] : null,
        );
    }
}
