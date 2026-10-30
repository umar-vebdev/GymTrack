<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Memberships\Contracts\CheckInMembershipCandidate;
use DateTimeImmutable;

/**
 * Контекст, передаваемый каждому правилу проверки.
 */
final class CheckInContext
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $gymId,
        public readonly int $clientId,
        public readonly DateTimeImmutable $now,
        public readonly CheckInMembershipCandidate $candidate,
    ) {}
}
