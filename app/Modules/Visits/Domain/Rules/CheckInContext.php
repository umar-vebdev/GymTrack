<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use DateTimeImmutable;

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
    ) {}
}
