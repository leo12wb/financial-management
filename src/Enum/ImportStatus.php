<?php

declare(strict_types=1);

namespace App\Enum;

enum ImportStatus: string
{
    case Pending   = 'pending';
    case Previewed = 'previewed';
    case Confirmed = 'confirmed';
    case Failed    = 'failed';
}
