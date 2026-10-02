<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Domain\UserRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final class VerifyPhone
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function execute(string $phone, string $code): void
    {
        $cachedCode = Cache::get("otp:{$phone}");

        if ($cachedCode !== $code) {
            throw new InvalidArgumentException('Неверный или устаревший код подтверждения.');
        }

        $user = $this->userRepository->findByPhone($phone);

        if ($user) {
            $user->markPhoneAsVerified();
            $this->userRepository->update($user);
        }

        Cache::forget("otp:{$phone}");
    }
}
