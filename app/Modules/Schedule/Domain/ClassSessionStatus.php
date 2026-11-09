<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain;

enum ClassSessionStatus: string
{
    case SCHEDULED = 'scheduled';
    case CANCELLED = 'cancelled';
}
