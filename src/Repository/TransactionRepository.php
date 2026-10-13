<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\ReconciliationStatus;
use App\Enum\TransactionSource;
use App\Enum\TransactionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Returns paginated transactions filtered by user and optional criteria.
     *
     * Supported keys in $filters:
     *   - accountId           (int)
     *   - categoryId          (int)
     *   - type                (TransactionType)
     *   - reconciliationStatus (ReconciliationStatus)
     *   - dateFrom            (string YYYY-MM-DD)
     *   - dateTo              (string YYYY-MM-DD)
     *   - search              (string — partial match on description)
     *   - source              (TransactionSource)
     *
     * @return array{data: Transaction[], total: int}
     */
    public function findByFilters(
        User $user,
        array $filters = [],
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.transactionDate', 'DESC')
            ->addOrderBy('t.id', 'DESC');

        if (isset($filters['accountId'])) {
            $qb->andWhere('t.account = :accountId')
               ->setParameter('accountId', (int) $filters['accountId']);
        }

        if (isset($filters['categoryId'])) {
            $qb->andWhere('t.category = :categoryId')
               ->setParameter('categoryId', (int) $filters['categoryId']);
        }

        if (isset($filters['type']) && $filters['type'] instanceof TransactionType) {
            $qb->andWhere('t.type = :type')
               ->setParameter('type', $filters['type']);
        }

        if (isset($filters['reconciliationStatus']) && $filters['reconciliationStatus'] instanceof ReconciliationStatus) {
            $qb->andWhere('t.reconciliationStatus = :reconciliationStatus')
               ->setParameter('reconciliationStatus', $filters['reconciliationStatus']);
        }

        if (isset($filters['dateFrom'])) {
            $qb->andWhere('t.transactionDate >= :dateFrom')
               ->setParameter('dateFrom', new \DateTime((string) $filters['dateFrom']));
        }

        if (isset($filters['dateTo'])) {
            $qb->andWhere('t.transactionDate <= :dateTo')
               ->setParameter('dateTo', new \DateTime((string) $filters['dateTo']));
        }

        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $qb->andWhere('t.description LIKE :search')
               ->setParameter('search', '%' . trim((string) $filters['search']) . '%');
        }

        if (isset($filters['source']) && $filters['source'] instanceof TransactionSource) {
            $qb->andWhere('t.source = :source')
               ->setParameter('source', $filters['source']);
        }

        // Clone the query but reset ORDER BY — PostgreSQL rejects aggregate
        // (COUNT) combined with ORDER BY on non-aggregated columns without GROUP BY.
        $countQb = clone $qb;
        $total   = (int) $countQb
            ->select('COUNT(t.id)')
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

    public function findOneByIdAndUser(int $id, User $user): ?Transaction
    {
        return $this->createQueryBuilder('t')
            ->join('t.account', 'a')
            ->andWhere('t.id = :id')
            ->andWhere('a.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calculates the current balance for an account:
     * initialBalance + SUM(income) - SUM(expense).
     */
    public function calculateAccountBalance(Account $account): string
    {
        $result = $this->getEntityManager()
            ->createQueryBuilder()
            ->select(
                'SUM(CASE WHEN t.type = :income THEN t.amount ELSE 0 END) - ' .
                'SUM(CASE WHEN t.type = :expense THEN t.amount ELSE 0 END) AS net'
            )
            ->from(Transaction::class, 't')
            ->andWhere('t.account = :account')
            ->setParameter('account', $account)
            ->setParameter('income', TransactionType::Income)
            ->setParameter('expense', TransactionType::Expense)
            ->getQuery()
            ->getSingleScalarResult();

        $net            = $result !== null ? (string) $result : '0';
        $initialBalance = $account->getInitialBalance();

        return number_format(
            (float) $initialBalance + (float) $net,
            2,
            '.',
            ''
        );
    }
}
