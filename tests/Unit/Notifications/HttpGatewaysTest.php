<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Modules\Identity\Contracts\SmsGateway;
use App\Modules\Identity\Infrastructure\HttpSmsGateway;
use App\Modules\Identity\Infrastructure\LogSmsGateway;
use App\Modules\Notifications\Domain\PushGatewayInterface;
use App\Modules\Notifications\Domain\SmsGatewayInterface;
use App\Modules\Notifications\Infrastructure\HttpNotificationsSmsGateway;
use App\Modules\Notifications\Infrastructure\HttpPushGateway;
use App\Modules\Notifications\Infrastructure\LogNotificationsSmsGateway;
use App\Modules\Notifications\Infrastructure\LogPushGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class HttpGatewaysTest extends TestCase
{
    public function test_container_resolves_log_drivers_by_default_and_switches_to_http(): void
    {
        config()->set('services.sms.driver', 'log');
        config()->set('services.push.driver', 'log');

        $this->assertInstanceOf(LogSmsGateway::class, $this->app->make(SmsGateway::class));
        $this->assertInstanceOf(LogNotificationsSmsGateway::class, $this->app->make(SmsGatewayInterface::class));
        $this->assertInstanceOf(LogPushGateway::class, $this->app->make(PushGatewayInterface::class));

        config()->set('services.sms', [
            'driver' => 'http',
            'url' => 'https://sms.example.test/send',
            'token' => 'sms-secret',
            'sender' => 'GymTrack',
            'timeout' => 7,
        ]);
        config()->set('services.push', [
            'driver' => 'http',
            'url' => 'https://push.example.test/send',
            'token' => 'push-secret',
            'timeout' => 6,
        ]);

        $this->assertInstanceOf(HttpSmsGateway::class, $this->app->make(SmsGateway::class));
        $this->assertInstanceOf(HttpNotificationsSmsGateway::class, $this->app->make(SmsGatewayInterface::class));
        $this->assertInstanceOf(HttpPushGateway::class, $this->app->make(PushGatewayInterface::class));
    }

    public function test_http_gateways_send_expected_json_requests_with_bearer_token(): void
    {
        Http::fake([
            'https://sms.example.test/send' => Http::response(['status' => 'ok'], 200),
            'https://push.example.test/send' => Http::response(['status' => 'ok'], 202),
        ]);

        config()->set('services.sms', [
            'driver' => 'http',
            'url' => 'https://sms.example.test/send',
            'token' => 'sms-token',
            'sender' => 'GymTrack',
            'timeout' => 5,
        ]);
        config()->set('services.push', [
            'driver' => 'http',
            'url' => 'https://push.example.test/send',
            'token' => 'push-token',
            'timeout' => 5,
        ]);

        /** @var SmsGateway $identitySms */
        $identitySms = $this->app->make(SmsGateway::class);
        $identitySms->send('+992900111222', 'Код: 123456');

        /** @var SmsGatewayInterface $notificationsSms */
        $notificationsSms = $this->app->make(SmsGatewayInterface::class);
        $notificationsSms->send('+992900333444', 'Напоминание');

        /** @var PushGatewayInterface $push */
        $push = $this->app->make(PushGatewayInterface::class);
        $push->send('device-token-1', 'Заголовок', 'Текст сообщения', ['visit_id' => 42]);

        Http::assertSentCount(3);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sms.example.test/send'
            && $request->hasHeader('Authorization', 'Bearer sms-token')
            && $request['phone'] === '+992900111222'
            && $request['message'] === 'Код: 123456'
            && $request['sender'] === 'GymTrack');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sms.example.test/send'
            && $request->hasHeader('Authorization', 'Bearer sms-token')
            && $request['phone'] === '+992900333444'
            && $request['message'] === 'Напоминание'
            && $request['sender'] === 'GymTrack');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://push.example.test/send'
            && $request->hasHeader('Authorization', 'Bearer push-token')
            && $request['recipient'] === 'device-token-1'
            && $request['title'] === 'Заголовок'
            && $request['body'] === 'Текст сообщения'
            && $request['payload'] === ['visit_id' => 42]);
    }

    public function test_http_gateways_fail_when_url_missing_or_remote_server_returns_error(): void
    {
        Http::fake([
            'https://sms.example.test/fail' => Http::response(['error' => 'gateway down'], 503),
            'https://push.example.test/fail' => Http::response(['error' => 'invalid token'], 401),
        ]);

        try {
            (new HttpSmsGateway(url: ''))->send('+992900111222', 'test');
            $this->fail('Ожидалось исключение при пустом SMS URL.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        try {
            (new HttpPushGateway(url: '   '))->send('tok', 't', 'b');
            $this->fail('Ожидалось исключение при пустом Push URL.');
        } catch (RuntimeException) {
            $this->addToAssertionCount(1);
        }

        try {
            (new HttpNotificationsSmsGateway(url: 'https://sms.example.test/fail'))->send('+992900111222', 'msg');
            $this->fail('Ожидалось RequestException при ответе 503 SMS шлюза.');
        } catch (RequestException) {
            $this->addToAssertionCount(1);
        }

        try {
            (new HttpPushGateway(url: 'https://push.example.test/fail'))->send('tok', 'title', 'body');
            $this->fail('Ожидалось RequestException при ответе 401 Push шлюза.');
        } catch (RequestException) {
            $this->addToAssertionCount(1);
        }
    }
}
