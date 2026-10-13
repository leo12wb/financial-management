<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\User;
use App\Enum\CategoryType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return Category[]
     */
    public function findByUser(User $user, ?CategoryType $type = null, bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC');

        if ($type !== null) {
            $qb->andWhere('c.type = :type')
               ->setParameter('type', $type);
        }

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByIdAndUser(int $id, User $user): ?Category
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.id = :id')
            ->andWhere('c.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function isUsedByTransactions(int $id): bool
    {
        $count = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from('App\Entity\Transaction', 't')
            ->andWhere('t.category = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }
}
