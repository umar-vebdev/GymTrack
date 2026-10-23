<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Strategies;

use App\Modules\Memberships\Domain\Exceptions\NoVisitsLeft;
use App\Modules\Memberships\Domain\Membership;
use DateTimeImmutable;

final class VisitsConsumptionStrategy implements ConsumptionStrategy
{
    public function consume(Membership $membership, DateTimeImmutable $at): void
    {
        $remaining = $membership->remainingVisits();

        if ($remaining === null || $remaining <= 0) {
            throw NoVisitsLeft::forMembership($membership->id());
        }

        $membership->decrementRemainingVisits();
    }
}
