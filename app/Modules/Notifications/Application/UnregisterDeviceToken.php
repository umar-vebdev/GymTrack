<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Domain\DeviceTokenRepositoryInterface;

final class UnregisterDeviceToken
{
    public function __construct(
        private readonly DeviceTokenRepositoryInterface $deviceTokens
    ) {}

    public function execute(int $userId, string $token): void
    {
        $this->deviceTokens->deleteToken($userId, $token);
    }
}
