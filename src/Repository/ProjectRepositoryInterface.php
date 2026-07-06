<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;

interface ProjectRepositoryInterface
{
    /**
     * @return list<Project>
     */
    public function findAccessibleByUser(User $user): array;
}
