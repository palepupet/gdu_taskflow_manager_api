<?php

declare(strict_types=1);

namespace App\Dto\Project;

use App\Enum\ProjectStatus;
use Symfony\Component\Validator\Constraints as Assert;

class SearchProjectRequest
{
    /**
     * @param list<string>|null $status
     */
    public function __construct(
        #[Assert\All([new Assert\Choice(callback: [ProjectStatus::class, 'values'])])]
        public ?array $status = null,
        public ?bool $archived = null,
        #[Assert\Choice(choices: ['id', 'title', 'status', 'createdAt', 'startAt', 'endAt'])]
        public ?string $sortField = null,
        #[Assert\Choice(choices: ['asc', 'desc'])]
        public ?string $sortOrder = null,
    ) {
    }

    /**
     * @SuppressWarnings("PHPMD.NPathComplexity")
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
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

        $status = null;
        if (isset($filters['status'])) {
            $status = \is_array($filters['status'])
                ? array_values(array_filter($filters['status'], '\is_string'))
                : (\is_string($filters['status']) ? [$filters['status']] : null);
        }

        $archived = null;
        if (array_key_exists('archived', $filters) && \is_bool($filters['archived'])) {
            $archived = $filters['archived'];
        }

        return new self(
            status: $status,
            archived: $archived,
            sortField: isset($sort['field']) && \is_string($sort['field']) ? $sort['field'] : null,
            sortOrder: isset($sort['order']) && \is_string($sort['order']) ? $sort['order'] : null,
        );
    }
}
