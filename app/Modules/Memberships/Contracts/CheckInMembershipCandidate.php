<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

use DateTimeImmutable;

/**
 * Data Transfer Object (DTO) representing a candidate membership for a check‑in.
 *
 * @property int $membershipId           Идентификатор абонемента.
 * @property int $remainingVisits       Осталось посещений (null – без ограничения).
 * @property DateTimeImmutable $expiresAt  Дата истечения (null – бессрочно).
 * @property bool $isFrozen               Заморожен ли абонемент.
 */
final class CheckInMembershipCandidate
{
    public function __construct(
        public readonly int $membershipId,
        public readonly ?int $remainingVisits,
        public readonly ?DateTimeImmutable $expiresAt,
        public readonly bool $isFrozen,
    ) {}
}
