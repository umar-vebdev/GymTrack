<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\SmsGateway;
use Illuminate\Support\Facades\Cache;

final class SendPhoneVerificationOtp
{
    public function __construct(
        private readonly SmsGateway $smsGateway
    ) {}

    public function execute(string $phone): void
    {
        $code = (string) random_int(100000, 999999);

        // Save in cache for 5 minutes
        Cache::put("otp:{$phone}", $code, now()->addMinutes(5));

        $this->smsGateway->send($phone, "Ваш код подтверждения: {$code}");
    }
}
