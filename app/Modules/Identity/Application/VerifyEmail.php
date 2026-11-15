<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\EmailVerified;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Сценарий проверки OTP-кода подтверждения email и публикации события EmailVerified.
 */
final class VerifyEmail
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly Clock $clock,
    ) {}

    public function execute(string $email, string $code): void
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $cachedCode = Cache::get("otp:email:{$normalizedEmail}");

        if ($cachedCode !== $code) {
            throw new InvalidArgumentException('Неверный или устаревший код подтверждения.');
        }

        $user = $this->userRepository->findByEmail($normalizedEmail);

        if ($user !== null) {
            $user->markEmailAsVerified($this->clock->now());
            $this->userRepository->update($user);
            Event::dispatch(new EmailVerified($user->id(), $normalizedEmail));
        }

        Cache::forget("otp:email:{$normalizedEmail}");
    }
}
