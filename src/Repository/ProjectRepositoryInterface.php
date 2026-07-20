<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Project\SearchProjectRequest;
use App\Entity\Project;
use App\Entity\User;

interface ProjectRepositoryInterface
{
    /**
     * @return list<Project>
     */
    public function findAccessibleByUser(User $user): array;

    public function findById(int $id): ?Project;

    /**
     * @return list<Project>
     */
    public function searchByUser(User $user, SearchProjectRequest $criteria): array;
}
