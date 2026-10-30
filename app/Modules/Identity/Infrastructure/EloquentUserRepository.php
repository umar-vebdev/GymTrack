<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Models\User as EloquentUser;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use DateTimeImmutable;

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
        bool $isPhoneVerified,
        ?string $name = null,
        ?DateTimeImmutable $birthDate = null,
        ?DateTimeImmutable $personalDataConsentedAt = null,
    ): int {
        $model = EloquentUser::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => $passwordHash,
            'is_phone_verified' => $isPhoneVerified,
            'birth_date' => $birthDate?->format('Y-m-d'),
            'personal_data_consented_at' => $personalDataConsentedAt?->format('Y-m-d H:i:s'),
        ]);

        return $model->id;
    }

    public function update(User $user): void
    {
        EloquentUser::where('id', $user->id())->update([
            'name' => $user->name(),
            'phone' => $user->phone(),
            'email' => $user->email(),
            'password' => $user->passwordHash(),
            'is_phone_verified' => $user->isPhoneVerified(),
            'birth_date' => $user->birthDate()?->format('Y-m-d'),
            'personal_data_consented_at' => $user->personalDataConsentedAt()?->format('Y-m-d H:i:s'),
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
            name: $model->name,
            birthDate: $model->birth_date !== null
                ? new DateTimeImmutable($model->birth_date->format('Y-m-d'))
                : null,
            personalDataConsentedAt: $model->personal_data_consented_at?->toDateTimeImmutable(),
        );
    }
}
