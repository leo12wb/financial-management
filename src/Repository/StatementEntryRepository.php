<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\ImportedStatement;
use App\Entity\StatementEntry;
use App\Enum\ReconciliationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StatementEntry>
 */
class StatementEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatementEntry::class);
    }

    /**
     * @return StatementEntry[]
     */
    public function findByStatement(ImportedStatement $statement, ?ReconciliationStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.importedStatement = :statement')
            ->setParameter('statement', $statement)
            ->orderBy('e.transactionDate', 'ASC');

        if ($status !== null) {
            $qb->andWhere('e.reconciliationStatus = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Detects a possible duplicate statement entry.
     *
     * If $externalRef is provided, the lookup is performed by externalReference
     * within entries belonging to the same account.
     * Otherwise it falls back to a combination of account + amount + date.
     */
    public function findDuplicate(
        Account $account,
        string $amount,
        \DateTimeInterface $date,
        ?string $externalRef
    ): ?StatementEntry {
        $qb = $this->createQueryBuilder('e')
            ->join('e.importedStatement', 's')
            ->andWhere('s.account = :account')
            ->setParameter('account', $account);

        if ($externalRef !== null && trim($externalRef) !== '') {
            $qb->andWhere('e.externalReference = :externalRef')
               ->setParameter('externalRef', $externalRef);
        } else {
            $qb->andWhere('e.amount = :amount')
               ->andWhere('e.transactionDate = :date')
               ->setParameter('amount', $amount)
               ->setParameter('date', $date);
        }

        return $qb->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
