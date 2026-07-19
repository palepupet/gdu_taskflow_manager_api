<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Task\SearchTaskRequest;
use App\Entity\Task;

interface TaskRepositoryInterface
{
    /**
     * @return list<Task>
     */
    public function findByProjectId(int $projectId): array;

    public function findById(int $id): ?Task;

    /**
     * @return list<Task>
     */
    public function searchByProjectId(int $projectId, SearchTaskRequest $criteria): array;
}
