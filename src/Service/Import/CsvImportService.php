<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Account;
use App\Entity\ImportedStatement;
use App\Entity\StatementEntry;
use App\Entity\Transaction;
use App\Enum\ImportStatus;
use App\Enum\ReconciliationStatus;
use App\Enum\TransactionSource;
use App\Enum\TransactionType;
use App\Repository\StatementEntryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CsvImportService
{
    private const REQUIRED_COLUMNS = ['date', 'description', 'amount', 'type'];
    private const MAX_FILE_SIZE    = 10 * 1024 * 1024; // 10 MB

    /**
     * @return array<string, mixed>
     */
    public function preview(
        Account $account,
        string $csvContent,
        string $originalFilename,
        EntityManagerInterface $em,
        StatementEntryRepository $entryRepo,
    ): array {
        if (strlen($csvContent) > self::MAX_FILE_SIZE) {
            throw new \DomainException('O arquivo CSV excede o tamanho máximo permitido de 10 MB.');
        }

        $lines = explode("\n", str_replace("\r\n", "\n", $csvContent));
        $lines = array_filter($lines, static fn (string $line): bool => trim($line) !== '');
        $lines = array_values($lines);

        if (count($lines) < 2) {
            throw new \DomainException('O arquivo CSV deve conter ao menos uma linha de cabeçalho e uma linha de dados.');
        }

        $headers = array_map(
            static fn (string $h): string => strtolower(trim($h)),
            str_getcsv(array_shift($lines))
        );

        foreach (self::REQUIRED_COLUMNS as $required) {
            if (!in_array($required, $headers, true)) {
                throw new \DomainException(
                    sprintf('Coluna obrigatória ausente no CSV: "%s". Colunas esperadas: %s.', $required, implode(', ', self::REQUIRED_COLUMNS))
                );
            }
        }

        $statement = new ImportedStatement();
        $statement->setAccount($account);
        $statement->setOriginalFilename($originalFilename);
        $statement->setStatus(ImportStatus::Previewed);
        $statement->setTotalRows(count($lines));

        $em->persist($statement);

        $entries      = [];
        $validRows    = 0;
        $invalidRows  = 0;
        $duplicateRows = 0;

        foreach ($lines as $line) {
            $row  = str_getcsv((string) $line);
            $data = $this->parseCsvLine($headers, $row);

            $error     = $this->validateRow($data);
            $isDuplicate = false;

            if ($error === null) {
                $date      = new \DateTime((string) $data['date']);
                $duplicate = $entryRepo->findDuplicate(
                    $account,
                    (string) $data['amount'],
                    $date,
                    $data['external_reference'] !== null ? (string) $data['external_reference'] : null
                );

                if ($duplicate !== null) {
                    $isDuplicate = true;
                    $duplicateRows++;
                    $error = 'Lançamento duplicado detectado.';
                }
            }

            $entry = new StatementEntry();
            $entry->setImportedStatement($statement);
            $entry->setDescription((string) ($data['description'] ?? ''));

            if ($error === null || !$isDuplicate) {
                $parsedDate = \DateTime::createFromFormat('Y-m-d', (string) ($data['date'] ?? ''));
                $entry->setTransactionDate($parsedDate !== false ? $parsedDate : new \DateTime());
                $entry->setAmount((string) ($data['amount'] ?? '0'));

                if ($data['external_reference'] !== null) {
                    $entry->setExternalReference((string) $data['external_reference']);
                }
            } else {
                $entry->setTransactionDate(new \DateTime());
                $entry->setAmount('0.00');
            }

            $em->persist($entry);

            $entryData = [
                'date'               => $data['date'] ?? null,
                'description'        => $data['description'] ?? null,
                'amount'             => $data['amount'] ?? null,
                'type'               => $data['type'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
                'error'              => $error,
                'isDuplicate'        => $isDuplicate,
            ];

            $entries[] = $entryData;

            if ($error === null) {
                $validRows++;
            } else {
                $invalidRows++;
            }
        }

        $em->flush();

        return [
            'importId'      => $statement->getId(),
            'totalRows'     => count($lines),
            'validRows'     => $validRows,
            'invalidRows'   => $invalidRows,
            'duplicateRows' => $duplicateRows,
            'entries'       => $entries,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function confirm(
        ImportedStatement $statement,
        EntityManagerInterface $em,
    ): array {
        if ($statement->getStatus() !== ImportStatus::Previewed) {
            throw new \DomainException('Apenas importações com status "previewed" podem ser confirmadas.');
        }

        $entries      = $statement->getStatementEntries();
        $importedRows = 0;
        $rejectedRows = 0;

        foreach ($entries as $entry) {
            /** @var StatementEntry $entry */
            if ($entry->getReconciliationStatus() !== ReconciliationStatus::Pending) {
                $rejectedRows++;
                continue;
            }

            if (empty($entry->getDescription()) || $entry->getAmount() === '0.00') {
                $rejectedRows++;
                continue;
            }

            $transaction = new Transaction();
            $transaction->setAccount($statement->getAccount());
            $transaction->setDescription($entry->getDescription());
            $transaction->setAmount($entry->getAmount());
            $transaction->setType(TransactionType::Income);
            $transaction->setTransactionDate($entry->getTransactionDate());
            $transaction->setExternalReference($entry->getExternalReference());
            $transaction->setSource(TransactionSource::Imported);
            $transaction->setReconciliationStatus(ReconciliationStatus::Pending);
            $transaction->setUpdatedAt(null);

            $em->persist($transaction);
            $importedRows++;
        }

        $statement->setImportedRows($importedRows);
        $statement->setRejectedRows($rejectedRows);
        $statement->setStatus(ImportStatus::Confirmed);

        $em->flush();

        return [
            'importedRows' => $importedRows,
            'rejectedRows' => $rejectedRows,
            'totalRows'    => $statement->getTotalRows(),
        ];
    }

    /**
     * @param string[] $headers
     * @param string[] $row
     * @return array<string, string|null>
     */
    private function parseCsvLine(array $headers, array $row): array
    {
        $indexed = [];

        foreach ($headers as $i => $header) {
            $indexed[$header] = isset($row[$i]) ? trim($row[$i]) : null;
        }

        return [
            'date'               => $indexed['date'] ?? null,
            'description'        => $indexed['description'] ?? null,
            'amount'             => $indexed['amount'] ?? null,
            'type'               => $indexed['type'] ?? null,
            'external_reference' => $indexed['external_reference'] ?? $indexed['reference'] ?? null,
        ];
    }

    /**
     * @param array<string, string|null> $data
     */
    private function validateRow(array $data): ?string
    {
        if (empty($data['date'])) {
            return 'O campo "date" é obrigatório.';
        }

        $parsedDate = \DateTime::createFromFormat('Y-m-d', (string) $data['date']);

        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $data['date']) {
            return 'O campo "date" deve estar no formato AAAA-MM-DD.';
        }

        if (empty($data['description'])) {
            return 'O campo "description" é obrigatório.';
        }

        if (empty($data['amount'])) {
            return 'O campo "amount" é obrigatório.';
        }

        if (!is_numeric($data['amount']) || (float) $data['amount'] <= 0) {
            return 'O campo "amount" deve ser um número positivo.';
        }

        if (empty($data['type'])) {
            return 'O campo "type" é obrigatório.';
        }

        if (!in_array($data['type'], ['income', 'expense'], true)) {
            return 'O campo "type" deve ser "income" ou "expense".';
        }

        return null;
    }
}
