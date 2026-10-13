<?php

declare(strict_types=1);

namespace App\Enum;

enum ReconciliationMatchStatus: string
{
    case Pending    = 'pending';
    case Reconciled = 'reconciled';
    case Rejected   = 'rejected';
    case Undone     = 'undone';
}
