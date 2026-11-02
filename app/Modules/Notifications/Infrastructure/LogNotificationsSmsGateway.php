<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure;

use App\Modules\Notifications\Domain\SmsGatewayInterface;
use Illuminate\Support\Facades\Log;

final class LogNotificationsSmsGateway implements SmsGatewayInterface
{
    public function send(string $phone, string $message): void
    {
        Log::info("SMS to {$phone}: {$message}");
    }
}
