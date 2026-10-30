<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain;

use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use DateTimeImmutable;

final class QrPayload
{
    public function __construct(
        public readonly int $gymId,
        public readonly int $slot,
        public readonly string $signature,
        public readonly DateTimeImmutable $expiresAt,
    ) {}

    public function toTokenString(): string
    {
        return "{$this->gymId}|{$this->slot}|{$this->signature}";
    }

    /**
     * @return array{gymId: int, slot: int, signature: string}
     *
     * @throws InvalidQrToken
     */
    public static function parseTokenString(string $raw): array
    {
        $parts = explode('|', trim($raw));

        if (count($parts) !== 3) {
            throw InvalidQrToken::invalid();
        }

        [$gymIdRaw, $slotRaw, $signature] = $parts;

        if (!ctype_digit($gymIdRaw) || !ctype_digit($slotRaw) || $signature === '') {
            throw InvalidQrToken::invalid();
        }

        return [
            'gymId' => (int) $gymIdRaw,
            'slot' => (int) $slotRaw,
            'signature' => $signature,
        ];
    }
}
