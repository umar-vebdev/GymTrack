<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP-адаптер отправки OTP и сервисных SMS через внешний шлюз (Бизнес-ТЗ 15.1; Тех. ТЗ 4.4, 16.3).
 */
final class HttpSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $token = null,
        private readonly ?string $sender = null,
        private readonly int $timeoutSeconds = 5,
    ) {}

    public function send(string $phone, string $message): void
    {
        $url = trim($this->url);
        if ($url === '') {
            throw new RuntimeException('SMS gateway URL is not configured.');
        }

        $payload = [
            'phone' => $phone,
            'message' => $message,
        ];

        if ($this->sender !== null && trim($this->sender) !== '') {
            $payload['sender'] = trim($this->sender);
        }

        $request = Http::timeout(max(1, $this->timeoutSeconds))->acceptJson();
        if ($this->token !== null && trim($this->token) !== '') {
            $request = $request->withToken(trim($this->token));
        }

        $request->post($url, $payload)->throw();
    }
}
