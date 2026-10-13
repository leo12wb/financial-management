<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReconciliationStatus;
use App\Enum\TransactionSource;
use App\Enum\TransactionType;
use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(columns: ['account_id', 'transaction_date'], name: 'idx_transaction_account_date')]
#[ORM\Index(columns: ['reconciliation_status'], name: 'idx_transaction_reconciliation_status')]
#[ORM\Index(columns: ['external_reference'], name: 'idx_transaction_external_reference')]
#[ORM\HasLifecycleCallbacks]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $description;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $amount;

    #[ORM\Column(type: Types::STRING, enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $transactionDate;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $externalReference = null;

    #[ORM\Column(type: Types::STRING, enumType: TransactionSource::class)]
    private TransactionSource $source = TransactionSource::Manual;

    #[ORM\Column(type: Types::STRING, enumType: ReconciliationStatus::class)]
    private ReconciliationStatus $reconciliationStatus = ReconciliationStatus::Pending;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt            = new \DateTimeImmutable();
        $this->source               = TransactionSource::Manual;
        $this->reconciliationStatus = ReconciliationStatus::Pending;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

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

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function setType(TransactionType $type): static
    {
        $this->type = $type;

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

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function setExternalReference(?string $externalReference): static
    {
        $this->externalReference = $externalReference;

        return $this;
    }

    public function getSource(): TransactionSource
    {
        return $this->source;
    }

    public function setSource(TransactionSource $source): static
    {
        $this->source = $source;

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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
