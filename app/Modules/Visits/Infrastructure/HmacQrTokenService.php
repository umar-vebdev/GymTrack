<?php

declare(strict_types=1);

namespace App\Modules\Visits\Infrastructure;

use App\Modules\Visits\Domain\ClientQrPayload;
use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use App\Modules\Visits\Domain\QrPayload;
use App\Modules\Visits\Domain\QrTokenServiceInterface;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class HmacQrTokenService implements QrTokenServiceInterface
{
    public function __construct(
        private readonly ?string $clientSecret = null,
    ) {}

    public function generate(int $gymId, string $secret, DateTimeImmutable $at): QrPayload
    {
        $slot = intdiv($at->getTimestamp(), self::INTERVAL_SECONDS);
        $signature = hash_hmac('sha256', "{$gymId}|{$slot}", $secret);

        return new QrPayload(
            gymId: $gymId,
            slot: $slot,
            signature: $signature,
            expiresAt: $this->slotExpiresAt($slot),
        );
    }

    public function verifyForGym(string $token, int $gymId, string $secret, DateTimeImmutable $at): void
    {
        $parsed = QrPayload::parseTokenString($token);

        if ($parsed['gymId'] !== $gymId) {
            throw InvalidQrToken::invalid();
        }

        $this->assertValidSlot($parsed['slot'], $at);

        $expectedSignature = hash_hmac('sha256', "{$gymId}|{$parsed['slot']}", $secret);

        if (!hash_equals($expectedSignature, $parsed['signature'])) {
            throw InvalidQrToken::invalid();
        }
    }

    public function generateForClient(int $userId, DateTimeImmutable $at): ClientQrPayload
    {
        $slot = intdiv($at->getTimestamp(), self::INTERVAL_SECONDS);
        $signature = hash_hmac('sha256', "client|{$userId}|{$slot}", $this->clientHmacKey($userId));

        return new ClientQrPayload(
            userId: $userId,
            slot: $slot,
            signature: $signature,
            expiresAt: $this->slotExpiresAt($slot),
        );
    }

    public function verifyClientToken(string $token, DateTimeImmutable $at): int
    {
        $parsed = ClientQrPayload::parseTokenString($token);

        $this->assertValidSlot($parsed['slot'], $at);

        $expectedSignature = hash_hmac(
            'sha256',
            "client|{$parsed['userId']}|{$parsed['slot']}",
            $this->clientHmacKey($parsed['userId']),
        );

        if (!hash_equals($expectedSignature, $parsed['signature'])) {
            throw InvalidQrToken::invalid();
        }

        return $parsed['userId'];
    }

    private function assertValidSlot(int $slot, DateTimeImmutable $at): void
    {
        $currentSlot = intdiv($at->getTimestamp(), self::INTERVAL_SECONDS);

        if ($slot !== $currentSlot && $slot !== ($currentSlot - 1)) {
            throw InvalidQrToken::expired();
        }
    }

    private function slotExpiresAt(int $slot): DateTimeImmutable
    {
        $expiresTimestamp = ($slot + 1) * self::INTERVAL_SECONDS;

        return (new DateTimeImmutable('@'.$expiresTimestamp))->setTimezone(new DateTimeZone('UTC'));
    }

    private function clientHmacKey(int $userId): string
    {
        if ($this->clientSecret === null || $this->clientSecret === '') {
            throw new RuntimeException('Секрет приложения для клиентского QR не настроен.');
        }

        return hash_hmac('sha256', "client-qr:{$userId}", $this->clientSecret, true);
    }
}
