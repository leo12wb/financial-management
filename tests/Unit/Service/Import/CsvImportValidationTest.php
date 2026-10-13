<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use PHPUnit\Framework\TestCase;

/**
 * Tests CSV row validation logic mirroring CsvImportService::validateRow.
 *
 * Rules enforced:
 *   - date       : required, format YYYY-MM-DD, must not be more than 5 years in the future
 *   - description: required, non-empty string
 *   - amount     : required, numeric, strictly positive (> 0)
 *   - type       : required, one of "income" or "expense"
 */
class CsvImportValidationTest extends TestCase
{
    /**
     * Validates a single CSV row.
     *
     * Returns null on success, or an error string on the first validation failure.
     *
     * @param array<string, string|null> $data
     */
    private function validateRow(array $data): ?string
    {
        // ----- date -----
        if (empty($data['date'])) {
            return 'O campo "date" é obrigatório.';
        }

        $parsedDate = \DateTime::createFromFormat('Y-m-d', (string) $data['date']);

        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $data['date']) {
            return 'O campo "date" deve estar no formato AAAA-MM-DD.';
        }

        // Reject dates more than 5 years in the future
        $maxFuture = new \DateTime('+5 years');
        if ($parsedDate > $maxFuture) {
            return 'O campo "date" não pode ser uma data excessivamente futura (mais de 5 anos).';
        }

        // ----- description -----
        if (empty($data['description']) || trim((string) $data['description']) === '') {
            return 'O campo "description" é obrigatório.';
        }

        // ----- amount -----
        if (empty($data['amount'])) {
            return 'O campo "amount" é obrigatório.';
        }

        if (!is_numeric($data['amount']) || (float) $data['amount'] <= 0) {
            return 'O campo "amount" deve ser um número positivo.';
        }

        // ----- type -----
        if (empty($data['type'])) {
            return 'O campo "type" é obrigatório.';
        }

        if (!in_array($data['type'], ['income', 'expense'], true)) {
            return 'O campo "type" deve ser "income" ou "expense".';
        }

        return null;
    }

    public function testValidRowPasses(): void
    {
        $row = [
            'date'        => '2024-01-15',
            'description' => 'Salário',
            'amount'      => '5000.00',
            'type'        => 'income',
        ];

        $this->assertNull($this->validateRow($row));
    }

    public function testValidExpenseRowPasses(): void
    {
        $row = [
            'date'        => '2024-03-20',
            'description' => 'Aluguel',
            'amount'      => '1200.50',
            'type'        => 'expense',
        ];

        $this->assertNull($this->validateRow($row));
    }

    public function testInvalidDateFormat(): void
    {
        $row = [
            'date'        => '15/01/2024', // wrong format — must be YYYY-MM-DD
            'description' => 'Pagamento',
            'amount'      => '100.00',
            'type'        => 'expense',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('date', $error);
    }

    public function testInvalidDateFormatDMY(): void
    {
        $row = [
            'date'        => '31-12-2024',
            'description' => 'Teste',
            'amount'      => '50.00',
            'type'        => 'income',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('date', $error);
    }

    public function testInvalidAmountNegative(): void
    {
        $row = [
            'date'        => '2024-06-01',
            'description' => 'Reembolso',
            'amount'      => '-100.00', // negative — must be positive
            'type'        => 'income',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('amount', $error);
    }

    public function testInvalidAmountZero(): void
    {
        $row = [
            'date'        => '2024-06-01',
            'description' => 'Taxa zero',
            'amount'      => '0', // zero — must be strictly positive
            'type'        => 'expense',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('amount', $error);
    }

    public function testInvalidAmountZeroDecimal(): void
    {
        $row = [
            'date'        => '2024-06-01',
            'description' => 'Taxa',
            'amount'      => '0.00',
            'type'        => 'expense',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('amount', $error);
    }

    public function testInvalidAmountNonNumeric(): void
    {
        $row = [
            'date'        => '2024-06-01',
            'description' => 'Compra',
            'amount'      => 'abc',
            'type'        => 'expense',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('amount', $error);
    }

    public function testInvalidType(): void
    {
        $row = [
            'date'        => '2024-06-01',
            'description' => 'Pagamento',
            'amount'      => '200.00',
            'type'        => 'payment', // invalid — must be income or expense
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('type', $error);
    }

    public function testInvalidTypeTransfer(): void
    {
        $row = [
            'date'        => '2024-06-01',
            'description' => 'Transferência',
            'amount'      => '500.00',
            'type'        => 'transfer',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('type', $error);
    }

    public function testMissingDescription(): void
    {
        $row = [
            'date'        => '2024-06-01',
            'description' => '',
            'amount'      => '300.00',
            'type'        => 'income',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('description', $error);
    }

    public function testMissingDescriptionWhitespaceOnly(): void
    {
        $row = [
            'date'        => '2024-06-01',
            'description' => '   ',
            'amount'      => '300.00',
            'type'        => 'income',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('description', $error);
    }

    public function testFutureDate(): void
    {
        // A date more than 5 years from now must be rejected
        $farFuture = (new \DateTime('+6 years'))->format('Y-m-d');

        $row = [
            'date'        => $farFuture,
            'description' => 'Planejamento futuro',
            'amount'      => '1000.00',
            'type'        => 'income',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('date', $error);
    }

    public function testNearFutureDateIsAccepted(): void
    {
        // Dates within the 5-year window should be valid
        $nearFuture = (new \DateTime('+1 year'))->format('Y-m-d');

        $row = [
            'date'        => $nearFuture,
            'description' => 'Compra futura',
            'amount'      => '500.00',
            'type'        => 'expense',
        ];

        $this->assertNull($this->validateRow($row));
    }

    public function testMissingDateField(): void
    {
        $row = [
            'date'        => '',
            'description' => 'Pagamento',
            'amount'      => '100.00',
            'type'        => 'expense',
        ];

        $error = $this->validateRow($row);

        $this->assertNotNull($error);
        $this->assertStringContainsString('date', $error);
    }
}
