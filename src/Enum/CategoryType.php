<?php

declare(strict_types=1);

namespace App\Enum;

enum CategoryType: string
{
    case Income  = 'income';
    case Expense = 'expense';
}
