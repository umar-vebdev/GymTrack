<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

use DateTimeImmutable;

final readonly class ExpiringMembershipDto
{
    public function __construct(
        public int $membershipId,
        public int $companyId,
        public int $clientId,
        public int $typeId,
        public ?DateTimeImmutable $endsAt
    ) {}
}
