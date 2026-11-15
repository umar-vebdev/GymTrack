<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain;

use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use DateTimeImmutable;

interface QrTokenServiceInterface
{
    public const int INTERVAL_SECONDS = 60;

    public function generate(int $gymId, string $secret, DateTimeImmutable $at): QrPayload;

    /**
     * Проверяет подпись и временной интервал токена зала (текущий или предыдущий слот).
     *
     * @throws InvalidQrToken
     */
    public function verifyForGym(string $token, int $gymId, string $secret, DateTimeImmutable $at): void;

    public function generateForClient(int $userId, DateTimeImmutable $at): ClientQrPayload;

    /**
     * Проверяет подпись и временной интервал личного QR-токена клиента (текущий или предыдущий слот) и возвращает ID пользователя.
     *
     * @throws InvalidQrToken
     */
    public function verifyClientToken(string $token, DateTimeImmutable $at): int;
}
