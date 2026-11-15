<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application\Commands;

final class CheckInByClientQrCommand
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $gymId,
        public readonly int $staffUserId,
        public readonly string $qrToken,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $device = null,
    ) {}
}
