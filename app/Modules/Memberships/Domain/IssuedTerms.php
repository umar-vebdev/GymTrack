<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

use DateTimeImmutable;

/**
 * Value Object: начальное состояние только что выданного абонемента.
 */
final class IssuedTerms
{
    public function __construct(
        public readonly MembershipStatus $status,
        public readonly ?int $remainingVisits,
        public readonly ?DateTimeImmutable $startsAt,
        public readonly ?DateTimeImmutable $endsAt
    ) {}
}
