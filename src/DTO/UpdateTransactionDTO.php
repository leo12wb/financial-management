<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateTransactionDTO
{
    #[Assert\Positive(message: 'O ID da conta deve ser um número positivo.')]
    public ?int $accountId = null;

    public ?int $categoryId = null;

    #[Assert\Length(max: 500, maxMessage: 'A descrição não pode ter mais de {{ limit }} caracteres.')]
    public ?string $description = null;

    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Valor inválido. Use o formato: 0.00 (somente valores positivos).'
    )]
    public ?string $amount = null;

    #[Assert\Choice(
        choices: ['income', 'expense'],
        message: 'O tipo deve ser "income" ou "expense".'
    )]
    public ?string $type = null;

    #[Assert\Date(message: 'A data deve estar no formato AAAA-MM-DD.')]
    public ?string $transactionDate = null;

    #[Assert\Length(max: 255, maxMessage: 'A referência externa não pode ter mais de {{ limit }} caracteres.')]
    public ?string $externalReference = null;
}
