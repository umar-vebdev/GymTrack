<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/**
 * Шлюз отправки одноразового кода подтверждения на электронную почту.
 */
interface EmailOtpGateway
{
    public function sendOtp(string $email, string $code): void;
}
