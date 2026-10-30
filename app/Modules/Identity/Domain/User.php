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

    public function markPhoneAsVerified(): void
    {
        $this->isPhoneVerified = true;
    }

    public function anonymize(string $invalidatedPasswordHash): void
    {
        $this->name = 'Deleted User';
        $this->phone = null;
        $this->email = null;
        $this->birthDate = null;
        $this->isPhoneVerified = false;
        $this->passwordHash = $invalidatedPasswordHash;
    }
}
