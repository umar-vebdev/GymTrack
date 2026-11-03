<?php

declare(strict_types=1);

namespace App\Modules\Visits\Contracts;

use DateTimeImmutable;

final readonly class ClientVisitItemDto
{
    public function __construct(
        public int $id,
        public int $companyId,
        public int $gymId,
        public int $clientId,
        public int $membershipId,
        public string $method,
        public string $status,
        public DateTimeImmutable $visitedAt,
        public ?string $cancelReason,
        public ?DateTimeImmutable $cancelledAt
    ) {}
}
