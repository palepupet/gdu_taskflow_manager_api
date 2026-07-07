<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository implements ProjectRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findAccessibleByUser(User $user): array
    {
        if ($user->isManager()) {
            return $this->findBy([], ['id' => 'DESC']);
        }

        /** @var list<Project> $projects */
        $projects = $this->createQueryBuilder('project')
            ->where('project.owner = :user')
            ->orWhere(':user MEMBER OF project.members')
            ->setParameter('user', $user)
            ->orderBy('project.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $projects;
    }

    public function findById(int $id): ?Project
    {
        /** @var Project|null $project */
        $project = parent::find($id);

        return $project;
    }
}
