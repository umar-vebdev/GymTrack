<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\ClientRegistrationPolicy;
use App\Modules\Identity\Domain\Exceptions\ClientRegistrationDenied;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use Illuminate\Support\Facades\Hash;

final class RegisterClient
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly SendEmailVerificationOtp $sendOtp,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws ClientRegistrationDenied
     */
    public function execute(
        string $email,
        string $name,
        string $password,
        DateTimeImmutable $birthDate,
        bool $consented,
        ?string $phone = null,
    ): int {
        $now = $this->clock->now();
        $normalizedEmail = mb_strtolower(trim($email));
        $normalizedPhone = $phone !== null && trim($phone) !== '' ? trim($phone) : null;

        ClientRegistrationPolicy::assertEligible($birthDate, $consented, $now);

        if ($this->userRepository->findByEmail($normalizedEmail) !== null) {
            throw ClientRegistrationDenied::emailAlreadyRegistered();
        }

        if ($normalizedPhone !== null && $this->userRepository->findByPhone($normalizedPhone) !== null) {
            throw ClientRegistrationDenied::phoneAlreadyRegistered();
        }

        $userId = $this->userRepository->create(
            phone: $normalizedPhone,
            email: $normalizedEmail,
            passwordHash: Hash::make($password),
            isPhoneVerified: false,
            name: $name,
            birthDate: $birthDate,
            personalDataConsentedAt: $now,
            emailVerifiedAt: null,
        );

        $this->sendOtp->execute($normalizedEmail);

        return $userId;
    }
}
