<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Enum\ReconciliationStatus;
use App\Enum\TransactionType;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class DashboardService
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSummary(User $user, string $dateFrom, string $dateTo): array
    {
        // Single aggregated query instead of N per-account balance queries
        $totalBalance = $this->transactionRepository->calculateTotalBalanceForUser($user);

        // Income in period
        $incomeResult = $this->em->createQueryBuilder()
            ->select('SUM(t.amount)')
            ->from('App\Entity\Transaction', 't')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.transactionDate >= :dateFrom')
            ->andWhere('t.transactionDate <= :dateTo')
            ->setParameter('user', $user)
            ->setParameter('type', TransactionType::Income)
            ->setParameter('dateFrom', new \DateTime($dateFrom))
            ->setParameter('dateTo', new \DateTime($dateTo))
            ->getQuery()
            ->getSingleScalarResult();

        $totalIncome = $incomeResult !== null ? number_format((float) $incomeResult, 2, '.', '') : '0.00';

        // Expenses in period
        $expenseResult = $this->em->createQueryBuilder()
            ->select('SUM(t.amount)')
            ->from('App\Entity\Transaction', 't')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.transactionDate >= :dateFrom')
            ->andWhere('t.transactionDate <= :dateTo')
            ->setParameter('user', $user)
            ->setParameter('type', TransactionType::Expense)
            ->setParameter('dateFrom', new \DateTime($dateFrom))
            ->setParameter('dateTo', new \DateTime($dateTo))
            ->getQuery()
            ->getSingleScalarResult();

        $totalExpenses = $expenseResult !== null ? number_format((float) $expenseResult, 2, '.', '') : '0.00';

        // Pending reconciliation count
        $pendingCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from('App\Entity\Transaction', 't')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('t.reconciliationStatus = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ReconciliationStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();

        // Reconciled count
        $reconciledCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from('App\Entity\Transaction', 't')
            ->join('t.account', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('t.reconciliationStatus = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ReconciliationStatus::Reconciled)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalBalance'               => $totalBalance,
            'totalIncome'                => $totalIncome,
            'totalExpenses'              => $totalExpenses,
            'pendingReconciliationCount' => $pendingCount,
            'reconciledCount'            => $reconciledCount,
            'periodStart'                => $dateFrom,
            'periodEnd'                  => $dateTo,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getChartData(User $user, int $months = 6): array
    {
        // Pre-build month slots so all months appear even when there are no transactions
        $slots = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $d           = new \DateTime("first day of -{$i} month");
            $key         = $d->format('Y-m');
            $slots[$key] = ['month' => $d->format('M/Y'), 'income' => 0.0, 'expense' => 0.0];
        }

        $dateFrom = (new \DateTime("first day of -" . ($months - 1) . " month"))->setTime(0, 0, 0);
        $dateTo   = (new \DateTime('last day of this month'))->setTime(23, 59, 59);

        // Single GROUP BY query instead of 2 queries × N months
        $rows = $this->em->getConnection()->fetchAllAssociative(
            "SELECT TO_CHAR(t.transaction_date, 'YYYY-MM') AS ym,
                    t.type,
                    SUM(t.amount) AS total
             FROM transactions t
             JOIN accounts a ON t.account_id = a.id
             WHERE a.user_id = :userId
               AND t.transaction_date >= :dateFrom
               AND t.transaction_date <= :dateTo
             GROUP BY ym, t.type
             ORDER BY ym ASC",
            [
                'userId'   => $user->getId(),
                'dateFrom' => $dateFrom->format('Y-m-d H:i:s'),
                'dateTo'   => $dateTo->format('Y-m-d H:i:s'),
            ]
        );

        foreach ($rows as $row) {
            $ym = $row['ym'];
            if (!isset($slots[$ym])) {
                continue;
            }
            if ($row['type'] === 'income') {
                $slots[$ym]['income'] = round((float) $row['total'], 2);
            } else {
                $slots[$ym]['expense'] = round((float) $row['total'], 2);
            }
        }

        return array_values($slots);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCategoryExpenses(User $user, string $dateFrom, string $dateTo): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('c.id, c.name, SUM(t.amount) AS total')
            ->from('App\Entity\Transaction', 't')
            ->join('t.account', 'a')
            ->leftJoin('t.category', 'c')
            ->andWhere('a.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.transactionDate >= :dateFrom')
            ->andWhere('t.transactionDate <= :dateTo')
            ->groupBy('c.id, c.name')
            ->orderBy('total', 'DESC')
            ->setParameter('user', $user)
            ->setParameter('type', TransactionType::Expense)
            ->setParameter('dateFrom', new \DateTime($dateFrom))
            ->setParameter('dateTo', new \DateTime($dateTo))
            ->getQuery()
            ->getResult();

        $grandTotal = array_sum(array_map(
            static fn (array $row): float => (float) $row['total'],
            $rows
        ));

        return array_map(static function (array $row) use ($grandTotal): array {
            $amount     = round((float) $row['total'], 2);
            $percentage = $grandTotal > 0 ? round(($amount / $grandTotal) * 100, 2) : 0.0;

            return [
                'categoryId'   => $row['id'],
                'categoryName' => $row['name'] ?? 'Sem categoria',
                'amount'       => $amount,
                'percentage'   => $percentage,
            ];
        }, $rows);
    }
}
