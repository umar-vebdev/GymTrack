<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_otp(): void
    {
        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => '+992999999999',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Код подтверждения отправлен.']);

        $this->assertTrue(Cache::has('otp:+992999999999'));
    }

    public function test_it_verifies_phone_and_updates_user(): void
    {
        $user = User::factory()->create([
            'phone' => '+992999999999',
            'is_phone_verified' => false,
        ]);

        Cache::put('otp:+992999999999', '123456');

        $response = $this->postJson('/api/v1/auth/verify-phone', [
            'phone' => '+992999999999',
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Телефон успешно подтвержден.']);

        $this->assertFalse(Cache::has('otp:+992999999999'));

        $user->refresh();
        $this->assertTrue($user->is_phone_verified);
    }

    public function test_it_fails_verification_with_wrong_code(): void
    {
        User::factory()->create([
            'phone' => '+992999999999',
            'is_phone_verified' => false,
        ]);

        Cache::put('otp:+992999999999', '123456');

        $response = $this->postJson('/api/v1/auth/verify-phone', [
            'phone' => '+992999999999',
            'code' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');

        $user = User::where('phone', '+992999999999')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_phone_verified);
    }
}
