<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

use DateTimeImmutable;

/**
 * DTO для других модулей: краткие данные об абонементе без доступа к внутренним классам Memberships.
 */
final class MembershipSummary
{
    public function __construct(
        public readonly int $id,
        public readonly int $typeId,
        public readonly string $status,
        public readonly ?int $remainingVisits,
        public readonly ?DateTimeImmutable $endsAt
    ) {}
}
