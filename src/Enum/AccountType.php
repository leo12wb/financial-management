<?php

declare(strict_types=1);

namespace App\Enum;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings  = 'savings';
    case Wallet   = 'wallet';

    public function label(): string
    {
        return match($this) {
            self::Checking => 'Conta Corrente',
            self::Savings  => 'Poupança',
            self::Wallet   => 'Carteira',
        };
    }
}
