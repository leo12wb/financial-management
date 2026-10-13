<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAccountDTO
{
    #[Assert\NotBlank(message: 'O nome é obrigatório.')]
    #[Assert\Length(max: 255, maxMessage: 'O nome não pode ter mais de {{ limit }} caracteres.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'O nome da instituição é obrigatório.')]
    #[Assert\Length(max: 255, maxMessage: 'O nome da instituição não pode ter mais de {{ limit }} caracteres.')]
    public string $institutionName = '';

    #[Assert\NotBlank(message: 'O tipo de conta é obrigatório.')]
    #[Assert\Choice(
        choices: ['checking', 'savings', 'wallet'],
        message: 'O tipo de conta deve ser "checking", "savings" ou "wallet".'
    )]
    public string $accountType = 'checking';

    #[Assert\NotBlank(message: 'A moeda é obrigatória.')]
    #[Assert\Length(
        min: 3,
        max: 3,
        exactMessage: 'A moeda deve ter exatamente {{ limit }} caracteres (ex: BRL).'
    )]
    public string $currency = 'BRL';

    #[Assert\NotBlank(message: 'O saldo inicial é obrigatório.')]
    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Valor inválido. Use o formato: 0.00'
    )]
    public string $initialBalance = '0.00';
}
