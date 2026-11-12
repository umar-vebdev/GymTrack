<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain;

enum ClassBookingStatus: string
{
    case BOOKED = 'booked';
    case CANCELLED = 'cancelled';
}
