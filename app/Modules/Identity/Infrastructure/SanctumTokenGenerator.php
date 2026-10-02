<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Models\User;
use App\Modules\Identity\Contracts\TokenGenerator;

final class SanctumTokenGenerator implements TokenGenerator
{
    public function generateForUser(int $userId, string $device): string
    {
        /** @var User $user */
        $user = User::findOrFail($userId);

        return $user->createToken($device)->plainTextToken;
    }
}
