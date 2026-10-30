<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use DateTimeImmutable;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByPhone(string $phone): ?User;

    public function findByEmail(string $email): ?User;

    /**
     * Creates a new user in the database.
     * Returns the generated ID.
     */
    public function create(
        ?string $phone,
        ?string $email,
        string $passwordHash,
        bool $isPhoneVerified,
        ?string $name = null,
        ?DateTimeImmutable $birthDate = null,
        ?DateTimeImmutable $personalDataConsentedAt = null,
    ): int;

    public function update(User $user): void;
}
