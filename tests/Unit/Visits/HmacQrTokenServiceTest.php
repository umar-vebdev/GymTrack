<?php

declare(strict_types=1);

namespace Tests\Unit\Visits;

use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use App\Modules\Visits\Infrastructure\HmacQrTokenService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class HmacQrTokenServiceTest extends TestCase
{
    public function test_it_accepts_current_and_previous_60s_slot_and_rejects_older_or_tampered_tokens(): void
    {
        $service = new HmacQrTokenService;
        $secret = 'super-secret-gym-key-1234567890';
        $gymId = 42;

        $generatedAt = new DateTimeImmutable('2026-10-30 12:00:10 UTC');
        $payload = $service->generate($gymId, $secret, $generatedAt);
        $token = $payload->toTokenString();

        // 1. В пределах той же минуты (текущий слот) — принимается
        $service->verifyForGym($token, $gymId, $secret, new DateTimeImmutable('2026-10-30 12:00:55 UTC'));

        // 2. В следующей минуте (предыдущий слот, допуск на задержку — Бизнес-ТЗ 6.1) — принимается
        $service->verifyForGym($token, $gymId, $secret, new DateTimeImmutable('2026-10-30 12:01:30 UTC'));

        // 3. Более 2 минут назад (критерий приёмки 12.3) — отказ с понятным сообщением
        try {
            $service->verifyForGym($token, $gymId, $secret, new DateTimeImmutable('2026-10-30 12:02:05 UTC'));
            $this->fail('Ожидался отказ для устаревшего QR-кода');
        } catch (InvalidQrToken $e) {
            $this->assertSame('Код недействителен, попросите администратора', $e->getMessage());
        }

        // 4. Чужой секрет или подделанная подпись — отказ
        try {
            $service->verifyForGym($token, $gymId, 'wrong-secret', new DateTimeImmutable('2026-10-30 12:00:20 UTC'));
            $this->fail('Ожидался отказ при неверной подписи');
        } catch (InvalidQrToken $e) {
            $this->assertSame('Код недействителен, попросите администратора', $e->getMessage());
        }

        // 5. Токен от другого зала — отказ
        try {
            $service->verifyForGym($token, 99, $secret, new DateTimeImmutable('2026-10-30 12:00:20 UTC'));
            $this->fail('Ожидался отказ для QR другого зала');
        } catch (InvalidQrToken $e) {
            $this->assertSame('Код недействителен, попросите администратора', $e->getMessage());
        }
    }
}
