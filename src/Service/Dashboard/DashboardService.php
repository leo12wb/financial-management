<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Enum\ReconciliationStatus;
use App\Enum\TransactionType;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class DashboardService
{
    public function __construct(
        private AccountRepository $accountRepository,
        private TransactionRepository $transactionRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getSummary(User $user, string $dateFrom, string $dateTo): array
    {
        // Total balance across all accounts
        $accounts     = $this->accountRepository->findByUser($user, true);
        $totalBalance = '0.00';

        foreach ($accounts as $account) {
            $balance      = $this->transactionRepository->calculateAccountBalance($account);
            $totalBalance = number_format((float) $totalBalance + (float) $balance, 2, '.', '');
        }

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
        $result = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $monthStart = new \DateTime("first day of -{$i} month");
            $monthEnd   = new \DateTime("last day of -{$i} month");

            $monthStart->setTime(0, 0, 0);
            $monthEnd->setTime(23, 59, 59);

            $incomeResult = $this->em->createQueryBuilder()
                ->select('SUM(t.amount)')
                ->from('App\Entity\Transaction', 't')
                ->join('t.account', 'a')
                ->andWhere('a.user = :user')
                ->andWhere('t.type = :type')
                ->andWhere('t.transactionDate >= :start')
                ->andWhere('t.transactionDate <= :end')
                ->setParameter('user', $user)
                ->setParameter('type', TransactionType::Income)
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $expenseResult = $this->em->createQueryBuilder()
                ->select('SUM(t.amount)')
                ->from('App\Entity\Transaction', 't')
                ->join('t.account', 'a')
                ->andWhere('a.user = :user')
                ->andWhere('t.type = :type')
                ->andWhere('t.transactionDate >= :start')
                ->andWhere('t.transactionDate <= :end')
                ->setParameter('user', $user)
                ->setParameter('type', TransactionType::Expense)
                ->setParameter('start', $monthStart)
                ->setParameter('end', $monthEnd)
                ->getQuery()
                ->getSingleScalarResult();

            $result[] = [
                'month'   => $monthStart->format('M/Y'),
                'income'  => $incomeResult !== null ? round((float) $incomeResult, 2) : 0.0,
                'expense' => $expenseResult !== null ? round((float) $expenseResult, 2) : 0.0,
            ];
        }

        return $result;
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
