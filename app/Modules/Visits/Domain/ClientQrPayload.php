<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain;

use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use DateTimeImmutable;

final class ClientQrPayload
{
    public function __construct(
        public readonly int $userId,
        public readonly int $slot,
        public readonly string $signature,
        public readonly DateTimeImmutable $expiresAt,
    ) {}

    public function toTokenString(): string
    {
        return "client|{$this->userId}|{$this->slot}|{$this->signature}";
    }

    /**
     * @return array{userId: int, slot: int, signature: string}
     *
     * @throws InvalidQrToken
     */
    public static function parseTokenString(string $raw): array
    {
        $parts = explode('|', trim($raw));

        if (count($parts) !== 4 || $parts[0] !== 'client') {
            throw InvalidQrToken::invalid();
        }

        [, $userIdRaw, $slotRaw, $signature] = $parts;

        if (!ctype_digit($userIdRaw) || (int) $userIdRaw <= 0 || !ctype_digit($slotRaw) || $signature === '') {
            throw InvalidQrToken::invalid();
        }

        return [
            'userId' => (int) $userIdRaw,
            'slot' => (int) $slotRaw,
            'signature' => $signature,
        ];
    }
}

