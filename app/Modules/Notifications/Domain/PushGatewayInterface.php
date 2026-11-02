<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

interface PushGatewayInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(string $recipient, string $title, string $body, array $payload = []): void;
}
