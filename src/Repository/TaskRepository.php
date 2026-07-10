<?php

declare(strict_types=1);

namespace App\Repository;

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
}
