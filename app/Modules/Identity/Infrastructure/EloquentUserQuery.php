<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Contracts\UserQueryInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;

final class EloquentUserQuery implements UserQueryInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $users
    ) {}

    public function findVerifiedUserIdByPhone(string $phone): ?int
    {
        $user = $this->users->findByPhone($phone);

        return $user !== null && $user->isPhoneVerified() ? $user->id() : null;
    }
}
