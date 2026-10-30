<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

use DateTimeImmutable;

/**
 * DTO выбранного для отметки абонемента: только то, что нужно модулю Visits для проверки правил.
 * Лимит входов в день и окно времени суток (в минутах от 00:00 UTC) задаются типом абонемента (Бизнес-ТЗ 4.3).
 */
final class CheckInMembershipCandidate
{
    public function __construct(
        public readonly int $membershipId,
        public readonly ?int $remainingVisits,
        public readonly ?DateTimeImmutable $expiresAt,
        public readonly bool $isFrozen,
        public readonly int $dailyLimit,
        public readonly ?int $timeFromMinute,
        public readonly ?int $timeToMinute,
    ) {}
}
