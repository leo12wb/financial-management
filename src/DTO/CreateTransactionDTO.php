<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTransactionDTO
{
    #[Assert\NotBlank(message: 'A conta é obrigatória.')]
    #[Assert\Positive(message: 'O ID da conta deve ser um número positivo.')]
    public int $accountId = 0;

    public ?int $categoryId = null;

    #[Assert\NotBlank(message: 'A descrição é obrigatória.')]
    #[Assert\Length(max: 500, maxMessage: 'A descrição não pode ter mais de {{ limit }} caracteres.')]
    public string $description = '';

    #[Assert\NotBlank(message: 'O valor é obrigatório.')]
    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Valor inválido. Use o formato: 0.00 (somente valores positivos).'
    )]
    public string $amount = '';

    #[Assert\NotBlank(message: 'O tipo é obrigatório.')]
    #[Assert\Choice(
        choices: ['income', 'expense'],
        message: 'O tipo deve ser "income" ou "expense".'
    )]
    public string $type = '';

    #[Assert\NotBlank(message: 'A data da transação é obrigatória.')]
    #[Assert\Date(message: 'A data deve estar no formato AAAA-MM-DD.')]
    public string $transactionDate = '';

    #[Assert\Length(max: 255, maxMessage: 'A referência externa não pode ter mais de {{ limit }} caracteres.')]
    public ?string $externalReference = null;
}
