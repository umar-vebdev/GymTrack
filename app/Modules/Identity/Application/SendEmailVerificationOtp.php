<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\EmailOtpGateway;
use Illuminate\Support\Facades\Cache;

/**
 * Сценарий генерации и отправки одноразового 6-значного OTP-кода на email.
 */
final class SendEmailVerificationOtp
{
    public function __construct(
        private readonly EmailOtpGateway $emailOtpGateway,
    ) {}

    public function execute(string $email): void
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $code = (string) random_int(100000, 999999);

        Cache::put("otp:email:{$normalizedEmail}", $code, now()->addMinutes(5));

        $this->emailOtpGateway->sendOtp($normalizedEmail, $code);
    }
}
