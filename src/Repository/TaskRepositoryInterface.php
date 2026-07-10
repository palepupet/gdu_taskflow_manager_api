<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;

interface TaskRepositoryInterface
{
    /**
     * @return list<Task>
     */
    public function findByProjectId(int $projectId): array;
}
