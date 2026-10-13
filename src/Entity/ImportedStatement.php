<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ImportStatus;
use App\Repository\ImportedStatementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImportedStatementRepository::class)]
#[ORM\Table(name: 'imported_statements')]
class ImportedStatement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Account $account;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $originalFilename;

    #[ORM\Column(type: Types::STRING, enumType: ImportStatus::class)]
    private ImportStatus $status = ImportStatus::Pending;

    #[ORM\Column(type: Types::INTEGER)]
    private int $totalRows = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $importedRows = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $rejectedRows = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\OneToMany(targetEntity: StatementEntry::class, mappedBy: 'importedStatement', cascade: ['remove'])]
    private Collection $statementEntries;

    public function __construct()
    {
        $this->createdAt        = new \DateTimeImmutable();
        $this->status           = ImportStatus::Pending;
        $this->statementEntries = new ArrayCollection();
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

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getStatus(): ImportStatus
    {
        return $this->status;
    }

    public function setStatus(ImportStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function setTotalRows(int $totalRows): static
    {
        $this->totalRows = $totalRows;

        return $this;
    }

    public function getImportedRows(): int
    {
        return $this->importedRows;
    }

    public function setImportedRows(int $importedRows): static
    {
        $this->importedRows = $importedRows;

        return $this;
    }

    public function getRejectedRows(): int
    {
        return $this->rejectedRows;
    }

    public function setRejectedRows(int $rejectedRows): static
    {
        $this->rejectedRows = $rejectedRows;

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

    public function getStatementEntries(): Collection
    {
        return $this->statementEntries;
    }
}
