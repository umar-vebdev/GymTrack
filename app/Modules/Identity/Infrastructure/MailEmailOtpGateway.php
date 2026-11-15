<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Contracts\EmailOtpGateway;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Почтовый адаптер отправки OTP-кода через стандартный Mailer Laravel (бесплатный SMTP/log).
 */
final class MailEmailOtpGateway implements EmailOtpGateway
{
    public function sendOtp(string $email, string $code): void
    {
        Mail::raw(
            "Ваш код подтверждения: {$code}",
            static function (Message $message) use ($email): void {
                $message->to($email)->subject('Код подтверждения GymTrack');
            }
        );
    }
}
