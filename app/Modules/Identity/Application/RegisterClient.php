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
        private readonly SendPhoneVerificationOtp $sendOtp,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws ClientRegistrationDenied
     */
    public function execute(
        string $phone,
        string $name,
        string $password,
        DateTimeImmutable $birthDate,
        bool $consented,
    ): int {
        $now = $this->clock->now();

        ClientRegistrationPolicy::assertEligible($birthDate, $consented, $now);

        if ($this->userRepository->findByPhone($phone) !== null) {
            throw ClientRegistrationDenied::phoneAlreadyRegistered();
        }

        $userId = $this->userRepository->create(
            phone: $phone,
            email: null,
            passwordHash: Hash::make($password),
            isPhoneVerified: false,
            name: $name,
            birthDate: $birthDate,
            personalDataConsentedAt: $now,
        );

        $this->sendOtp->execute($phone);

        return $userId;
    }
}
