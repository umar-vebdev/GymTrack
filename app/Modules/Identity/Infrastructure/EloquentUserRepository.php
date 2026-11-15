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
        $normalized = mb_strtolower(trim($email));
        $model = EloquentUser::whereRaw('LOWER(email) = ?', [$normalized])->first();

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
        ?DateTimeImmutable $emailVerifiedAt = null,
    ): int {
        $normalizedEmail = $email !== null ? mb_strtolower(trim($email)) : null;

        $model = EloquentUser::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $normalizedEmail,
            'email_verified_at' => $emailVerifiedAt?->format('Y-m-d H:i:s'),
            'password' => $passwordHash,
            'is_phone_verified' => $isPhoneVerified,
            'birth_date' => $birthDate?->format('Y-m-d'),
            'personal_data_consented_at' => $personalDataConsentedAt?->format('Y-m-d H:i:s'),
        ]);

        return $model->id;
    }

    public function update(User $user): void
    {
        $email = $user->email();
        $normalizedEmail = $email !== null ? mb_strtolower(trim($email)) : null;

        EloquentUser::where('id', $user->id())->update([
            'name' => $user->name(),
            'phone' => $user->phone(),
            'email' => $normalizedEmail,
            'email_verified_at' => $user->emailVerifiedAt()?->format('Y-m-d H:i:s'),
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
            emailVerifiedAt: $model->email_verified_at?->toDateTimeImmutable(),
        );
    }
}
