<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

final class BookClassSessionCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly int $sessionId,
        public readonly ?string $idempotencyKey = null,
    ) {}
}
