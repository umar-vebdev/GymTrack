<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure;

use App\Modules\Notifications\Domain\PushGatewayInterface;
use Illuminate\Support\Facades\Log;

final class LogPushGateway implements PushGatewayInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(string $recipient, string $title, string $body, array $payload = []): void
    {
        Log::info("PUSH to {$recipient}: [{$title}] {$body}", $payload);
    }
}
