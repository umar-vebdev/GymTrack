<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

use DateTimeImmutable;

final readonly class ClientMembershipItemDto
{
    public function __construct(
        public int $id,
        public int $companyId,
        public int $clientId,
        public int $typeId,
        public string $typeName,
        public string $kind,
        public string $status,
        public ?int $remainingVisits,
        public ?DateTimeImmutable $startsAt,
        public ?DateTimeImmutable $endsAt,
        public ?DateTimeImmutable $frozenAt,
        public int $freezeDaysUsed,
        public int $freezeTimesUsed,
        public bool $isUsable
    ) {}
}

