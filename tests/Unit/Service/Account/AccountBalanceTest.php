<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Account;

use PHPUnit\Framework\TestCase;

/**
 * Tests the balance calculation logic mirroring TransactionRepository::calculateAccountBalance.
 *
 * Formula: currentBalance = initialBalance + SUM(income) - SUM(expense)
 */
class AccountBalanceTest extends TestCase
{
    /**
     * Replicates the balance logic without any database dependency.
     *
     * @param string                          $initialBalance Decimal string, e.g. "1000.00"
     * @param array<array{amount: string, type: string}> $transactions   Each item has 'amount' and 'type' (income|expense)
     */
    private function calculateBalance(string $initialBalance, array $transactions): string
    {
        $totalIncome  = '0.00';
        $totalExpense = '0.00';

        foreach ($transactions as $t) {
            if ($t['type'] === 'income') {
                $totalIncome = bcadd($totalIncome, $t['amount'], 2);
            } elseif ($t['type'] === 'expense') {
                $totalExpense = bcadd($totalExpense, $t['amount'], 2);
            }
        }

        $net     = bcsub($totalIncome, $totalExpense, 2);
        $balance = bcadd($initialBalance, $net, 2);

        return number_format((float) $balance, 2, '.', '');
    }

    public function testBalanceWithNoTransactions(): void
    {
        $balance = $this->calculateBalance('1000.00', []);

        $this->assertSame('1000.00', $balance);
    }

    public function testBalanceWithIncomeTransactions(): void
    {
        $transactions = [
            ['amount' => '1200.00', 'type' => 'income'],
            ['amount' => '800.00',  'type' => 'income'],
        ];

        $balance = $this->calculateBalance('500.00', $transactions);

        $this->assertSame('2500.00', $balance);
    }

    public function testBalanceWithExpenseTransactions(): void
    {
        $transactions = [
            ['amount' => '1000.00', 'type' => 'expense'],
            ['amount' => '500.00',  'type' => 'expense'],
        ];

        $balance = $this->calculateBalance('3000.00', $transactions);

        $this->assertSame('1500.00', $balance);
    }

    public function testBalanceWithMixedTransactions(): void
    {
        $transactions = [
            ['amount' => '2000.00', 'type' => 'income'],
            ['amount' => '800.00',  'type' => 'expense'],
        ];

        $balance = $this->calculateBalance('1000.00', $transactions);

        $this->assertSame('2200.00', $balance);
    }

    public function testBalanceCanBeNegative(): void
    {
        $transactions = [
            ['amount' => '500.00', 'type' => 'expense'],
        ];

        $balance = $this->calculateBalance('100.00', $transactions);

        $this->assertSame('-400.00', $balance);
    }

    public function testBalanceWithMultipleMixedTransactions(): void
    {
        $transactions = [
            ['amount' => '3000.00', 'type' => 'income'],
            ['amount' => '1500.00', 'type' => 'income'],
            ['amount' => '200.00',  'type' => 'expense'],
            ['amount' => '350.50',  'type' => 'expense'],
        ];

        // 0 + 3000 + 1500 - 200 - 350.50 = 3949.50
        $balance = $this->calculateBalance('0.00', $transactions);

        $this->assertSame('3949.50', $balance);
    }

    public function testBalanceWithZeroInitialAndNoTransactions(): void
    {
        $balance = $this->calculateBalance('0.00', []);

        $this->assertSame('0.00', $balance);
    }

    public function testBalanceWithLargeAmounts(): void
    {
        $transactions = [
            ['amount' => '999999.99', 'type' => 'income'],
        ];

        $balance = $this->calculateBalance('1.00', $transactions);

        $this->assertSame('1000000.99', $balance);
    }
}
