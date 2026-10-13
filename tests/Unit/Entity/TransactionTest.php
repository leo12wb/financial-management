<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Transaction;
use App\Enum\ReconciliationStatus;
use App\Enum\TransactionSource;
use App\Enum\TransactionType;
use PHPUnit\Framework\TestCase;

/**
 * Tests invariants and default values of the Transaction entity.
 *
 * These tests are purely in-memory: no database or Doctrine involvement.
 */
class TransactionTest extends TestCase
{
    public function testNewTransactionHasManualSourceAndPendingStatus(): void
    {
        $transaction = new Transaction();

        $this->assertSame(
            TransactionSource::Manual,
            $transaction->getSource(),
            'A newly created Transaction must default to TransactionSource::Manual.',
        );

        $this->assertSame(
            ReconciliationStatus::Pending,
            $transaction->getReconciliationStatus(),
            'A newly created Transaction must default to ReconciliationStatus::Pending.',
        );
    }

    public function testNewTransactionCreatedAtIsSetAutomatically(): void
    {
        $before      = new \DateTimeImmutable();
        $transaction = new Transaction();
        $after       = new \DateTimeImmutable();

        $createdAt = $transaction->getCreatedAt();

        $this->assertGreaterThanOrEqual(
            $before->getTimestamp(),
            $createdAt->getTimestamp(),
            'createdAt must not be earlier than the instant before construction.',
        );

        $this->assertLessThanOrEqual(
            $after->getTimestamp(),
            $createdAt->getTimestamp(),
            'createdAt must not be later than the instant after construction.',
        );
    }

    public function testNewTransactionUpdatedAtIsNullByDefault(): void
    {
        $transaction = new Transaction();

        $this->assertNull(
            $transaction->getUpdatedAt(),
            'updatedAt must be null for a brand-new Transaction.',
        );
    }

    public function testImportedTransactionSource(): void
    {
        $transaction = new Transaction();
        $transaction->setSource(TransactionSource::Imported);

        $this->assertSame(TransactionSource::Imported, $transaction->getSource());
    }

    public function testTransactionTypeIncome(): void
    {
        $transaction = new Transaction();
        $transaction->setType(TransactionType::Income);
        $transaction->setAmount('500.00');

        $this->assertSame(TransactionType::Income, $transaction->getType());
        $this->assertSame('500.00', $transaction->getAmount());
    }

    public function testTransactionTypeExpense(): void
    {
        $transaction = new Transaction();
        $transaction->setType(TransactionType::Expense);
        $transaction->setAmount('250.75');

        $this->assertSame(TransactionType::Expense, $transaction->getType());
        $this->assertSame('250.75', $transaction->getAmount());
    }

    public function testSetReconciliationStatusReconciled(): void
    {
        $transaction = new Transaction();
        $transaction->setReconciliationStatus(ReconciliationStatus::Reconciled);

        $this->assertSame(ReconciliationStatus::Reconciled, $transaction->getReconciliationStatus());
    }

    public function testSetReconciliationStatusDivergent(): void
    {
        $transaction = new Transaction();
        $transaction->setReconciliationStatus(ReconciliationStatus::Divergent);

        $this->assertSame(ReconciliationStatus::Divergent, $transaction->getReconciliationStatus());
    }

    public function testSetReconciliationStatusIgnored(): void
    {
        $transaction = new Transaction();
        $transaction->setReconciliationStatus(ReconciliationStatus::Ignored);

        $this->assertSame(ReconciliationStatus::Ignored, $transaction->getReconciliationStatus());
    }

    public function testSetDescription(): void
    {
        $transaction = new Transaction();
        $transaction->setDescription('Pagamento de aluguel');

        $this->assertSame('Pagamento de aluguel', $transaction->getDescription());
    }

    public function testSetTransactionDate(): void
    {
        $transaction = new Transaction();
        $date        = new \DateTime('2024-06-15');
        $transaction->setTransactionDate($date);

        $this->assertSame('2024-06-15', $transaction->getTransactionDate()->format('Y-m-d'));
    }

    public function testSetExternalReference(): void
    {
        $transaction = new Transaction();
        $transaction->setExternalReference('REF-001');

        $this->assertSame('REF-001', $transaction->getExternalReference());
    }

    public function testExternalReferenceIsNullByDefault(): void
    {
        $transaction = new Transaction();

        $this->assertNull($transaction->getExternalReference());
    }

    public function testSetNullExternalReference(): void
    {
        $transaction = new Transaction();
        $transaction->setExternalReference('REF-001');
        $transaction->setExternalReference(null);

        $this->assertNull($transaction->getExternalReference());
    }

    public function testFluentSetters(): void
    {
        $transaction = new Transaction();

        $result = $transaction
            ->setDescription('Teste')
            ->setAmount('100.00')
            ->setType(TransactionType::Income)
            ->setSource(TransactionSource::Imported)
            ->setReconciliationStatus(ReconciliationStatus::Pending);

        // All fluent setters must return the same instance
        $this->assertSame($transaction, $result);
    }
}
