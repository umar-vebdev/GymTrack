<?php

declare(strict_types=1);

namespace App\Modules\Visits\Infrastructure;

use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use App\Modules\Visits\Domain\QrPayload;
use App\Modules\Visits\Domain\QrTokenServiceInterface;
use DateTimeImmutable;
use DateTimeZone;

final class HmacQrTokenService implements QrTokenServiceInterface
{
    public function generate(int $gymId, string $secret, DateTimeImmutable $at): QrPayload
    {
        $slot = intdiv($at->getTimestamp(), self::INTERVAL_SECONDS);
        $signature = hash_hmac('sha256', "{$gymId}|{$slot}", $secret);
        $expiresTimestamp = ($slot + 1) * self::INTERVAL_SECONDS;
        $expiresAt = (new DateTimeImmutable('@'.$expiresTimestamp))->setTimezone(new DateTimeZone('UTC'));

        return new QrPayload(
            gymId: $gymId,
            slot: $slot,
            signature: $signature,
            expiresAt: $expiresAt,
        );
    }

    public function verifyForGym(string $token, int $gymId, string $secret, DateTimeImmutable $at): void
    {
        $parsed = QrPayload::parseTokenString($token);

        if ($parsed['gymId'] !== $gymId) {
            throw InvalidQrToken::invalid();
        }

        $currentSlot = intdiv($at->getTimestamp(), self::INTERVAL_SECONDS);
        $slot = $parsed['slot'];

        if ($slot !== $currentSlot && $slot !== ($currentSlot - 1)) {
            throw InvalidQrToken::expired();
        }

        $expectedSignature = hash_hmac('sha256', "{$gymId}|{$slot}", $secret);

        if (!hash_equals($expectedSignature, $parsed['signature'])) {
            throw InvalidQrToken::invalid();
        }
    }
}
