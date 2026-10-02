<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

final class User
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $phone,
        private readonly ?string $email,
        private readonly string $passwordHash,
        private bool $isPhoneVerified
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

    public function markPhoneAsVerified(): void
    {
        $this->isPhoneVerified = true;
    }
}
