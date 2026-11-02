<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

interface SmsGatewayInterface
{
    public function send(string $phone, string $message): void;
}
