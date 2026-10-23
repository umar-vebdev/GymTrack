<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Strategies;

use App\Modules\Memberships\Domain\MembershipKind;

final class ConsumptionStrategyResolver
{
    public static function resolve(MembershipKind $kind): ConsumptionStrategy
    {
        return match ($kind) {
            MembershipKind::Visits => new VisitsConsumptionStrategy,
            MembershipKind::Duration,
            MembershipKind::Unlimited,
            MembershipKind::Single => new TimeBasedConsumptionStrategy,
        };
    }
}
