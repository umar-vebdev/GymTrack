<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application\Commands;

use DateTimeImmutable;

final class CheckInManuallyCommand
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $gymId,
        public readonly int $clientId,
        public readonly int $staffUserId,
        public readonly ?DateTimeImmutable $visitedAt = null,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $device = null,
    ) {}
}
