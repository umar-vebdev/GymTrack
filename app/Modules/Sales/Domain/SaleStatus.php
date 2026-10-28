<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

enum SaleStatus: string
{
    case Completed = 'completed';
    case Refunded = 'refunded';
}
