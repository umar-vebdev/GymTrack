<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application\Commands;

use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\StartMode;

/**
 * Входной DTO сценария создания типа абонемента.
 */
final class CreateMembershipTypeCommand
{
    public function __construct(
        public readonly int $companyId,
        public readonly string $name,
        public readonly int $priceMinor,
        public readonly string $currency,
        public readonly MembershipKind $kind,
        public readonly StartMode $startMode,
        public readonly ?int $visitsCount = null,
        public readonly ?int $durationDays = null,
        public readonly ?int $validityDays = null,
        public readonly int $dailyLimit = 1,
        public readonly ?int $timeFromMinute = null,
        public readonly ?int $timeToMinute = null,
        public readonly int $freezeMaxDays = 0,
        public readonly int $freezeMaxTimes = 0
    ) {}
}
