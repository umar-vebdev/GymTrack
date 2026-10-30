<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain;

enum VisitStatus: string
{
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
