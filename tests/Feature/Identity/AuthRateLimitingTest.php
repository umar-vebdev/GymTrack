<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_per_phone_and_returns_unified_429(): void
    {
        User::factory()->create([
            'phone' => '+992900000111',
            'password' => Hash::make('secret123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'phone' => '+992900000111',
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        // 6-я попытка на тот же телефон блокируется с 429
        $throttled = $this->postJson('/api/v1/auth/login', [
            'phone' => '+992 900 000 111',
            'password' => 'secret123',
        ], [
            'Accept-Language' => 'tg',
        ]);

        $throttled->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('Content-Language', 'tg')
            ->assertJson([
                'error' => [
                    'code' => 'TOO_MANY_REQUESTS',
                    'message' => 'Дархостҳо хеле зиёданд. Лутфан баъдтар кӯшиш кунед.',
                ],
            ]);

        // Другой номер с того же IP не заблокирован лимитом конкретного телефона
        User::factory()->create([
            'phone' => '+992900000222',
            'password' => Hash::make('secret123'),
        ]);
        $this->postJson('/api/v1/auth/login', [
            'phone' => '+992900000222',
            'password' => 'secret123',
        ])->assertOk();
    }

    public function test_send_otp_and_verify_phone_are_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/send-otp', [
                'phone' => '+992900000333',
            ])->assertOk();
        }

        $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '+992900000333',
        ])->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS')
            ->assertJsonPath('error.message', 'Слишком много запросов. Попробуйте позже.');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/verify-phone', [
                'phone' => '+992900000333',
                'code' => '000000',
            ])->assertStatus(400);
        }

        $this->postJson('/api/v1/auth/verify-phone', [
            'phone' => '+992900000333',
            'code' => '000000',
        ])->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }

    public function test_register_endpoints_share_ip_rate_limit(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $phone = sprintf('+992900100%03d', $i);
            $this->postJson('/api/v1/client/auth/register', [
                'phone' => $phone,
                'name' => 'Client '.$i,
                'password' => 'secret1234',
                'birth_date' => '1995-05-01',
                'consent' => true,
            ])->assertCreated();
        }

        $this->postJson('/api/v1/auth/register', [
            'phone' => '+992900100999',
            'password' => 'secret1234',
        ])->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
    }
}
