<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAccountDTO
{
    #[Assert\Length(max: 255, maxMessage: 'O nome não pode ter mais de {{ limit }} caracteres.')]
    public ?string $name = null;

    #[Assert\Length(max: 255, maxMessage: 'O nome da instituição não pode ter mais de {{ limit }} caracteres.')]
    public ?string $institutionName = null;

    #[Assert\Choice(
        choices: ['checking', 'savings', 'wallet'],
        message: 'O tipo de conta deve ser "checking", "savings" ou "wallet".'
    )]
    public ?string $accountType = null;

    #[Assert\Length(
        min: 3,
        max: 3,
        exactMessage: 'A moeda deve ter exatamente {{ limit }} caracteres (ex: BRL).'
    )]
    public ?string $currency = null;

    #[Assert\Regex(
        pattern: '/^\d+(\.\d{1,2})?$/',
        message: 'Valor inválido. Use o formato: 0.00'
    )]
    public ?string $initialBalance = null;

    public ?bool $isActive = null;
}
