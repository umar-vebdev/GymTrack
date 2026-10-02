<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $model = EloquentUser::find($id);

        return $model ? $this->mapToDomain($model) : null;
    }

    public function findByPhone(string $phone): ?User
    {
        $model = EloquentUser::where('phone', $phone)->first();

        return $model ? $this->mapToDomain($model) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $model = EloquentUser::where('email', $email)->first();

        return $model ? $this->mapToDomain($model) : null;
    }

    public function create(
        ?string $phone,
        ?string $email,
        string $passwordHash,
        bool $isPhoneVerified
    ): int {
        $model = EloquentUser::create([
            'phone' => $phone,
            'email' => $email,
            'password' => $passwordHash,
            'is_phone_verified' => $isPhoneVerified,
        ]);

        return $model->id;
    }

    public function update(User $user): void
    {
        EloquentUser::where('id', $user->id())->update([
            'phone' => $user->phone(),
            'email' => $user->email(),
            'password' => $user->passwordHash(),
            'is_phone_verified' => $user->isPhoneVerified(),
        ]);
    }

    private function mapToDomain(EloquentUser $model): User
    {
        return new User(
            id: $model->id,
            phone: $model->phone,
            email: $model->email,
            passwordHash: $model->password,
            isPhoneVerified: $model->is_phone_verified,
        );
    }
}
