<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReconciliationStatus;
use App\Repository\StatementEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatementEntryRepository::class)]
#[ORM\Table(name: 'statement_entries')]
#[ORM\Index(columns: ['imported_statement_id', 'reconciliation_status'], name: 'idx_statement_entry_statement_status')]
class StatementEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: ImportedStatement::class, inversedBy: 'statementEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ImportedStatement $importedStatement;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $transactionDate;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $description;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $amount;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $externalReference = null;

    #[ORM\Column(type: Types::STRING, enumType: ReconciliationStatus::class)]
    private ReconciliationStatus $reconciliationStatus = ReconciliationStatus::Pending;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt            = new \DateTimeImmutable();
        $this->reconciliationStatus = ReconciliationStatus::Pending;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getImportedStatement(): ImportedStatement
    {
        return $this->importedStatement;
    }

    public function setImportedStatement(ImportedStatement $importedStatement): static
    {
        $this->importedStatement = $importedStatement;

        return $this;
    }

    public function getTransactionDate(): \DateTimeInterface
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(\DateTimeInterface $transactionDate): static
    {
        $this->transactionDate = $transactionDate;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): static
    {
        $this->externalReference = $externalReference;

        return $this;
    }

    public function getReconciliationStatus(): ReconciliationStatus
    {
        return $this->reconciliationStatus;
    }

    public function setReconciliationStatus(ReconciliationStatus $reconciliationStatus): static
    {
        $this->reconciliationStatus = $reconciliationStatus;

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
