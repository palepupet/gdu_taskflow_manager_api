<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Project\SearchProjectRequest;
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

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     *
     * @return list<Project>
     */
    public function searchByUser(User $user, SearchProjectRequest $criteria): array
    {
        $query = $this->createQueryBuilder('project');

        if (!$user->isManager()) {
            $query->where('project.owner = :user')
                ->orWhere(':user MEMBER OF project.members')
                ->setParameter('user', $user);
        }

        if (!empty($criteria->status)) {
            $query->andWhere('project.status IN (:statuses)')
                ->setParameter('statuses', $criteria->status);
        }

        if (null !== $criteria->archived) {
            $query->andWhere('project.isArchived = :archived')
                ->setParameter('archived', $criteria->archived);
        }

        $sortField = $criteria->sortField ?? 'id';
        $sortOrder = strtoupper($criteria->sortOrder ?? 'desc');
        $allowedFields = ['id', 'title', 'status', 'createdAt', 'startAt', 'endAt'];

        if (!\in_array($sortField, $allowedFields, true)) {
            $sortField = 'id';
        }

        if (!\in_array($sortOrder, ['ASC', 'DESC'], true)) {
            $sortOrder = 'DESC';
        }

        $query->orderBy('project.'.$sortField, $sortOrder);

        /** @var list<Project> $projects */
        $projects = $query->getQuery()->getResult();

        return $projects;
    }
}
