<?php

declare(strict_types=1);

namespace App\Enum;

enum TransactionSource: string
{
    case Manual   = 'manual';
    case Imported = 'imported';
}
