<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Данные для проверки отметки. Содержит только простые значения: Domain не зависит от других модулей,
 * а Use Case сам переносит сюда поля выбранного абонемента из контракта Memberships.
 */
final class CheckInContext
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $gymId,
        public readonly int $clientId,
        public readonly DateTimeImmutable $now,
        public readonly ?int $remainingVisits,
        public readonly ?DateTimeImmutable $membershipExpiresAt,
        public readonly bool $membershipFrozen,
        public readonly int $dailyLimit,
        public readonly ?int $timeFromMinute,
        public readonly ?int $timeToMinute,
        public readonly string $gymTimezone = 'UTC',
        public readonly bool $geoCheckEnabled = false,
        public readonly int $geoCheckRadiusMeters = 200,
        public readonly ?float $gymLatitude = null,
        public readonly ?float $gymLongitude = null,
        public readonly ?float $clientLatitude = null,
        public readonly ?float $clientLongitude = null,
    ) {}

    public function localNow(): DateTimeImmutable
    {
        return $this->now->setTimezone(new DateTimeZone($this->gymTimezone));
    }
}
