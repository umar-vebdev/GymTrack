<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Domain\DeviceTokenRepositoryInterface;

final class RegisterDeviceToken
{
    public function __construct(
        private readonly DeviceTokenRepositoryInterface $deviceTokens
    ) {}

    public function execute(int $userId, string $token, string $platform): void
    {
        $this->deviceTokens->upsertToken($userId, $token, $platform);
    }
}
