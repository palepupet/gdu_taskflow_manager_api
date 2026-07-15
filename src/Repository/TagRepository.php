<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository implements TagRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function findById(int $id): ?Tag
    {
        /** @var Tag|null $tag */
        $tag = $this->find($id);

        return $tag;
    }

    public function findByProjectId(int $projectId): array
    {
        /** @var list<Tag> $tags */
        $tags = $this->createQueryBuilder('tag')
            ->andWhere('IDENTITY(tag.project) = :projectId')
            ->setParameter('projectId', $projectId)
            ->orderBy('tag.label', 'ASC')
            ->getQuery()
            ->getResult();

        return $tags;
    }

    public function isTagAlreadyExistsWithThisLabel(
        string $label,
        int $projectId,
        ?int $excludeTagId = null,
    ): bool {
        $query = $this->createQueryBuilder('tag')
            ->select('1')
            ->andWhere('IDENTITY(tag.project) = :projectId')
            ->andWhere('tag.label = :label')
            ->setParameter('projectId', $projectId)
            ->setParameter('label', $label);

        if (null !== $excludeTagId) {
            $query->andWhere('tag.id != :excludeTagId')
                ->setParameter('excludeTagId', $excludeTagId);
        }

        return null !== $query
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
