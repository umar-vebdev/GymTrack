<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

final class LogSmsGateway implements SmsGateway
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS to {$phone}: {$message}");
    }
}
