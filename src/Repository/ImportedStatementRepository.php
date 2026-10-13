<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ImportedStatement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImportedStatement>
 */
class ImportedStatementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportedStatement::class);
    }

    /**
     * Returns paginated imported statements belonging to the given user.
     *
     * @return array{data: ImportedStatement[], total: int}
     */
    public function findByUser(User $user, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.account', 'a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.createdAt', 'DESC');

        $countQb = clone $qb;
        $total   = (int) $countQb->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $offset = ($page - 1) * $limit;

        $data = $qb->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'data'  => $data,
            'total' => $total,
        ];
    }

    public function findOneByIdAndUser(int $id, User $user): ?ImportedStatement
    {
        return $this->createQueryBuilder('s')
            ->join('s.account', 'a')
            ->andWhere('s.id = :id')
            ->andWhere('a.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
