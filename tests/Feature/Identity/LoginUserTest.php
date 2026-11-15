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

    public function test_it_returns_token_with_valid_phone_credentials(): void
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

    public function test_it_returns_token_with_valid_email_credentials_case_insensitive(): void
    {
        User::factory()->create([
            'email' => 'member@example.test',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'Member@Example.TEST',
            'password' => 'secret123',
            'device' => 'mobile',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_it_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'member@example.test',
            'phone' => '+992999999999',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.test',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_it_fails_with_non_existing_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'phone' => '+992000000000',
            'password' => 'secret123',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_it_fails_validation_when_both_email_and_phone_are_missing(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'password' => 'secret123',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_logout_revokes_only_current_device_token(): void
    {
        User::factory()->create([
            'email' => 'multi@example.test',
            'password' => Hash::make('secret123'),
        ]);

        $phoneToken = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'multi@example.test',
            'password' => 'secret123',
            'device' => 'phone',
        ])->assertOk()->json('data.token');

        $tabletToken = (string) $this->postJson('/api/v1/auth/login', [
            'email' => 'multi@example.test',
            'password' => 'secret123',
            'device' => 'tablet',
        ])->assertOk()->json('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withToken($phoneToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.logged_out', true);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->app->make('auth')->forgetGuards();

        $this->withToken($phoneToken)
            ->getJson('/api/v1/client/profile')
            ->assertStatus(401);

        $this->app->make('auth')->forgetGuards();

        $this->withToken($tabletToken)
            ->getJson('/api/v1/client/profile')
            ->assertOk();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
