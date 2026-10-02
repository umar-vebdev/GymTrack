<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_token_with_valid_credentials(): void
    {
        User::factory()->create([
            'phone' => '+992999999999',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+992999999999',
            'password' => 'secret123',
            'device' => 'test-device',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_it_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'phone' => '+992999999999',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+992999999999',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_it_fails_with_non_existing_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+992000000000',
            'password' => 'secret123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
