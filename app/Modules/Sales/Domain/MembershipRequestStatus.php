<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

enum MembershipRequestStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isTerminal(): bool
    {
        return $this !== self::PENDING;
    }
}
