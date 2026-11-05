<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

/**
 * Публичное представление активного типа абонемента для карточки зала в модуле Catalog (Бизнес-ТЗ 4.3, 7; D-010).
 */
final class PublicMembershipType
{
    public function __construct(
        public readonly string $publicId,
        public readonly string $name,
        public readonly string $kind,
        public readonly string $startMode,
        public readonly int $priceMinor,
        public readonly string $currency,
        public readonly ?int $visitsCount,
        public readonly ?int $durationDays,
        public readonly ?int $validityDays,
        public readonly int $dailyLimit,
        public readonly ?int $timeFromMinute,
        public readonly ?int $timeToMinute,
        public readonly int $freezeMaxDays,
        public readonly int $freezeMaxTimes,
    ) {}
}

