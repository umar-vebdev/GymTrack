<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure;

use App\Modules\Notifications\Domain\DeviceTokenRepositoryInterface;
use App\Modules\Notifications\Infrastructure\Persistence\DeviceTokenModel;

final class EloquentDeviceTokenRepository implements DeviceTokenRepositoryInterface
{
    public function upsertToken(int $userId, string $token, string $platform): void
    {
        DeviceTokenModel::query()->updateOrCreate(
            ['user_id' => $userId, 'token' => $token],
            ['platform' => $platform]
        );
    }

    public function deleteToken(int $userId, string $token): void
    {
        DeviceTokenModel::query()
            ->where('user_id', $userId)
            ->where('token', $token)
            ->delete();
    }

    /**
     * @return list<string>
     */
    public function findTokensForUser(int $userId): array
    {
        /** @var list<string> */
        return DeviceTokenModel::query()
            ->where('user_id', $userId)
            ->pluck('token')
            ->all();
    }
}
