<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;

interface TagRepositoryInterface
{
    public function findById(int $id): ?Tag;

    /**
     * @return list<Tag>
     */
    public function findByProjectId(int $projectId): array;

    public function isTagAlreadyExistsWithThisLabel(string $label, int $projectId): bool;
}
