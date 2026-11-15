<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\EmailVerified;
use App\Modules\Identity\Contracts\TokenGenerator;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

/**
 * Сценарий сброса пароля пользователя по 6-значному Email OTP (решение D-021).
 */
final class ResetPasswordWithOtp
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly TokenGenerator $tokenGenerator,
        private readonly Clock $clock,
    ) {}

    public function execute(string $email, string $code, string $newPassword): void
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $cachedCode = Cache::get("otp:email:{$normalizedEmail}");

        if ($cachedCode !== $code) {
            throw new InvalidArgumentException('Неверный или устаревший код подтверждения.');
        }

        $user = $this->userRepository->findByEmail($normalizedEmail);
        if ($user === null) {
            throw new InvalidArgumentException('Неверный или устаревший код подтверждения.');
        }

        $wasUnverified = !$user->isEmailVerified();

        $user->changePasswordHash(Hash::make($newPassword));
        if ($wasUnverified) {
            $user->markEmailAsVerified($this->clock->now());
        }

        $this->userRepository->update($user);

        if ($wasUnverified) {
            Event::dispatch(new EmailVerified($user->id(), $normalizedEmail));
        }

        $this->tokenGenerator->revokeAllForUser($user->id());
        Cache::forget("otp:email:{$normalizedEmail}");
    }
}
