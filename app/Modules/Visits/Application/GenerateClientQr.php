<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Visits\Domain\ClientQrPayload;
use App\Modules\Visits\Domain\QrTokenServiceInterface;
use App\Shared\Domain\Clock;

/**
 * Генерация личного динамического QR-кода клиента для сканирования на ресепшене зала (Бизнес-ТЗ 6.3, 11).
 */
final class GenerateClientQr
{
    public function __construct(
        private readonly QrTokenServiceInterface $qrService,
        private readonly Clock $clock,
    ) {}

    /**
     * @return array{payload: ClientQrPayload, ttlSeconds: int}
     */
    public function execute(int $userId): array
    {
        return [
            'payload' => $this->qrService->generateForClient($userId, $this->clock->now()),
            'ttlSeconds' => QrTokenServiceInterface::INTERVAL_SECONDS,
        ];
    }
}
