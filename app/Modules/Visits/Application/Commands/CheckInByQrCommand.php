<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application\Commands;

final class CheckInByQrCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly string $qrToken,
        public readonly ?string $idempotencyKey = null,
        public readonly ?string $device = null,
    ) {}
}
