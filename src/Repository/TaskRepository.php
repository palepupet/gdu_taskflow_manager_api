<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\Task\SearchTaskRequest;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository implements TaskRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function findByProjectId(int $projectId): array
    {
        /** @var list<Task> $tasks */
        $tasks = $this->createQueryBuilder('task')
            ->andWhere('IDENTITY(task.project) = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('task.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $tasks;
    }

    public function findById(int $id): ?Task
    {
        /** @var Task|null $task */
        $task = $this->find($id);

        return $task;
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     *
     * @return list<Task>
     */
    public function searchByProjectId(int $projectId, SearchTaskRequest $criteria): array
    {
        $query = $this->createQueryBuilder('task')
            ->andWhere('IDENTITY(task.project) = :projectId')
            ->setParameter('projectId', $projectId);

        if (!empty($criteria->state)) {
            $query->andWhere('task.state IN (:states)')
                ->setParameter('states', $criteria->state);
        }

        if (!empty($criteria->priority)) {
            $query->andWhere('task.priority IN (:priorities)')
                ->setParameter('priorities', $criteria->priority);
        }

        if (null !== $criteria->dueBefore) {
            $query->andWhere('task.dueAt IS NOT NULL')
                ->andWhere('task.dueAt <= :dueBefore')
                ->setParameter(
                    'dueBefore',
                    new \DateTimeImmutable($criteria->dueBefore.' 23:59:59'),
                );
        }

        if (!empty($criteria->tags)) {
            $query->innerJoin('task.tags', 'tag')
                ->andWhere('tag.id IN (:tagIds)')
                ->setParameter('tagIds', $criteria->tags)
                ->distinct();
        }

        if (null !== $criteria->assignee) {
            $query->andWhere('IDENTITY(task.assignee) = :assigneeId')
                ->setParameter('assigneeId', $criteria->assignee);
        }

        $sortField = $criteria->sortField ?? 'id';
        $sortOrder = strtoupper($criteria->sortOrder ?? 'desc');
        $allowedFields = ['id', 'dueAt', 'priority', 'state', 'createdAt', 'title'];

        if (!\in_array($sortField, $allowedFields, true)) {
            $sortField = 'id';
        }
        if (!\in_array($sortOrder, ['ASC', 'DESC'], true)) {
            $sortOrder = 'DESC';
        }

        $query->orderBy('task.'.$sortField, $sortOrder);

        /** @var list<Task> $tasks */
        $tasks = $query->getQuery()->getResult();

        return $tasks;
    }
}
