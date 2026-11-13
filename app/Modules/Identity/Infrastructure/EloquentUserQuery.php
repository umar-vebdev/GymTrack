<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Models\User;
use App\Modules\Identity\Contracts\UserQueryInterface;
use App\Modules\Identity\Contracts\UserSummary;
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

    public function findSummaryById(int $userId): ?UserSummary
    {
        $user = $this->users->findById($userId);

        if ($user === null) {
            return null;
        }

        return new UserSummary(
            id: $user->id(),
            name: $user->name(),
            phone: $user->phone(),
            email: $user->email(),
        );
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, UserSummary>
     */
    public function findSummariesByIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $models = User::query()
            ->whereIn('id', array_values(array_unique($userIds)))
            ->get(['id', 'name', 'phone', 'email']);

        $result = [];
        foreach ($models as $model) {
            $result[(int) $model->id] = new UserSummary(
                id: (int) $model->id,
                name: $model->name,
                phone: $model->phone,
                email: $model->email,
            );
        }

        return $result;
    }
}
