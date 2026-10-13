<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reconciliation;
use App\Entity\StatementEntry;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\ReconciliationMatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reconciliation>
 */
class ReconciliationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reconciliation::class);
    }

    /**
     * Returns paginated reconciliations belonging to the given user.
     *
     * @return array{data: Reconciliation[], total: int}
     */
    public function findByUser(User $user, int $page = 1, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('r')
            ->join('r.transaction', 't')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC');

        $countQb = clone $qb;
        $total   = (int) $countQb->select('COUNT(r.id)')
            ->resetDQLPart('orderBy')
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

    /**
     * Returns the active (non-rejected, non-undone) reconciliation for a transaction, if any.
     */
    public function findActiveByTransaction(Transaction $transaction): ?Reconciliation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.transaction = :transaction')
            ->andWhere('r.status NOT IN (:inactiveStatuses)')
            ->setParameter('transaction', $transaction)
            ->setParameter('inactiveStatuses', [
                ReconciliationMatchStatus::Rejected,
                ReconciliationMatchStatus::Undone,
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Returns the active (non-rejected, non-undone) reconciliation for a statement entry, if any.
     */
    public function findActiveByStatementEntry(StatementEntry $entry): ?Reconciliation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.statementEntry = :entry')
            ->andWhere('r.status NOT IN (:inactiveStatuses)')
            ->setParameter('entry', $entry)
            ->setParameter('inactiveStatuses', [
                ReconciliationMatchStatus::Rejected,
                ReconciliationMatchStatus::Undone,
            ])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
