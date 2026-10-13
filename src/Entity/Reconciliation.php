<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReconciliationMatchStatus;
use App\Repository\ReconciliationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReconciliationRepository::class)]
#[ORM\Table(name: 'reconciliations')]
#[ORM\UniqueConstraint(name: 'uq_reconciliation_transaction_entry', columns: ['transaction_id', 'statement_entry_id'])]
class Reconciliation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Transaction $transaction;

    #[ORM\ManyToOne(targetEntity: StatementEntry::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private StatementEntry $statementEntry;

    #[ORM\Column(type: Types::STRING, enumType: ReconciliationMatchStatus::class)]
    private ReconciliationMatchStatus $status = ReconciliationMatchStatus::Pending;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reconciledAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $undoneAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->status    = ReconciliationMatchStatus::Pending;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function setTransaction(Transaction $transaction): static
    {
        $this->transaction = $transaction;

        return $this;
    }

    public function getStatementEntry(): StatementEntry
    {
        return $this->statementEntry;
    }

    public function setStatementEntry(StatementEntry $statementEntry): static
    {
        $this->statementEntry = $statementEntry;

        return $this;
    }

    public function getStatus(): ReconciliationMatchStatus
    {
        return $this->status;
    }

    public function setStatus(ReconciliationMatchStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getReconciledAt(): ?\DateTimeImmutable
    {
        return $this->reconciledAt;
    }

    public function setReconciledAt(?\DateTimeImmutable $reconciledAt): static
    {
        $this->reconciledAt = $reconciledAt;

        return $this;
    }

    public function getUndoneAt(): ?\DateTimeImmutable
    {
        return $this->undoneAt;
    }

    public function setUndoneAt(?\DateTimeImmutable $undoneAt): static
    {
        $this->undoneAt = $undoneAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
