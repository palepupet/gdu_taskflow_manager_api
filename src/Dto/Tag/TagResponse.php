<?php

declare(strict_types=1);

namespace App\Dto\Tag;

use App\Entity\Project;
use App\Entity\Tag;

class TagResponse
{
    public function __construct(
        public int $id,
        public string $label,
        public int $projectId,
        public string $createdAt,
    ) {
    }

    public static function fromTag(Tag $tag): self
    {
        $id = $tag->getId();
        $project = $tag->getProject();
        $createdAt = $tag->getCreatedAt();

        if (null === $id || !$project instanceof Project || null === $createdAt) {
            throw new \LogicException('Un tag persisté doit avoir un id, un projet et une date de création.');
        }

        $projectId = $project->getId();
        if (null === $projectId) {
            throw new \LogicException('Le projet doit avoir un id.');
        }

        return new self(
            id: $id,
            label: (string) $tag->getLabel(),
            projectId: $projectId,
            createdAt: $createdAt->format(\DateTimeInterface::ATOM),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'projectId' => $this->projectId,
            'createdAt' => $this->createdAt,
        ];

    }
}
