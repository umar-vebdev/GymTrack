<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

final class RegisterUser
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(?string $phone, ?string $email, string $password): int
    {
        if (empty($phone) && empty($email)) {
            throw new InvalidArgumentException('Phone or email is required.');
        }

        $passwordHash = Hash::make($password);

        return $this->userRepository->create(
            phone: $phone,
            email: $email,
            passwordHash: $passwordHash,
            isPhoneVerified: false
        );
    }
}
