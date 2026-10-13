<?php

declare(strict_types=1);

namespace App\Enum;

enum ReconciliationStatus: string
{
    case Pending    = 'pending';
    case Reconciled = 'reconciled';
    case Divergent  = 'divergent';
    case Ignored    = 'ignored';
}
