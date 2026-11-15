<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use DateTimeImmutable;

final class User
{
    public function __construct(
        private readonly int $id,
        private ?string $phone,
        private ?string $email,
        private string $passwordHash,
        private bool $isPhoneVerified,
        private ?string $name = null,
        private ?DateTimeImmutable $birthDate = null,
        private ?DateTimeImmutable $personalDataConsentedAt = null,
        private ?DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function isPhoneVerified(): bool
    {
        return $this->isPhoneVerified;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function birthDate(): ?DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function personalDataConsentedAt(): ?DateTimeImmutable
    {
        return $this->personalDataConsentedAt;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function markPhoneAsVerified(): void
    {
        $this->isPhoneVerified = true;
    }

    public function markEmailAsVerified(DateTimeImmutable $at): void
    {
        $this->emailVerifiedAt = $at;
    }

    public function anonymize(string $invalidatedPasswordHash): void
    {
        $this->name = 'Deleted User';
        $this->phone = null;
        $this->email = null;
        $this->birthDate = null;
        $this->isPhoneVerified = false;
        $this->emailVerifiedAt = null;
        $this->passwordHash = $invalidatedPasswordHash;
    }
}
