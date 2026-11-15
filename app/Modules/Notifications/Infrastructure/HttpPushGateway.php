<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure;

use App\Modules\Notifications\Domain\PushGatewayInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP-адаптер отправки push-уведомлений через внешний шлюз или прокси FCM/APNs (Тех. ТЗ 4.4, 16.3).
 */
final class HttpPushGateway implements PushGatewayInterface
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $token = null,
        private readonly int $timeoutSeconds = 5,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(string $recipient, string $title, string $body, array $payload = []): void
    {
        $url = trim($this->url);
        if ($url === '') {
            throw new RuntimeException('Push gateway URL is not configured.');
        }

        $request = Http::timeout(max(1, $this->timeoutSeconds))->acceptJson();
        if ($this->token !== null && trim($this->token) !== '') {
            $request = $request->withToken(trim($this->token));
        }

        $request->post($url, [
            'recipient' => $recipient,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
        ])->throw();
    }
}
