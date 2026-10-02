<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class RegisterUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_user_with_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+992999999999',
            'password' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'message']]);

        $this->assertDatabaseHas('users', [
            'phone' => '+992999999999',
            'is_phone_verified' => false,
        ]);

        $user = User::where('phone', '+992999999999')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_it_fails_validation_without_phone_or_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_it_fails_when_phone_already_exists(): void
    {
        User::factory()->create(['phone' => '+992000000000']);

        $response = $this->postJson('/api/v1/auth/register', [
            'phone' => '+992000000000',
            'password' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.details.phone.0', 'The phone has already been taken.');
    }
}
