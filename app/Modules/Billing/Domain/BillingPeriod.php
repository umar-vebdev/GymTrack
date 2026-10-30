<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain;

use DateTimeImmutable;

enum BillingPeriod: string
{
    case Month = 'month';
    case Year = 'year';

    public function extendDate(DateTimeImmutable $from): DateTimeImmutable
    {
        return match ($this) {
            self::Month => $from->modify('+1 month'),
            self::Year => $from->modify('+1 year'),
        };
    }
}
