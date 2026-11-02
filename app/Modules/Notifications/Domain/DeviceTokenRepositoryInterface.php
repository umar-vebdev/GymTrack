<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

interface DeviceTokenRepositoryInterface
{
    public function upsertToken(int $userId, string $token, string $platform): void;

    public function deleteToken(int $userId, string $token): void;

    /**
     * @return list<string>
     */
    public function findTokensForUser(int $userId): array;
}
