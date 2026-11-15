<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ClientUserLinkingTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'client@example.test';

    private const PHONE = '+992900111222';

    private function staff(): User
    {
        $staff = User::factory()->create();
        $this->actingAs($staff, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'Co', 'gym_name' => 'Gym',
        ])->assertStatus(201);

        return $staff;
    }

    private function createClient(User $staff, string $phone = self::PHONE, ?string $email = self::EMAIL): void
    {
        $payload = ['name' => 'John', 'phone' => $phone];
        if ($email !== null) {
            $payload['email'] = $email;
        }

        $this->actingAs($staff, 'sanctum')->postJson('/api/v1/business/clients', $payload)
            ->assertStatus(201);
    }

    public function test_client_is_linked_on_creation_if_verified_user_exists(): void
    {
        $user = User::factory()->create([
            'email' => self::EMAIL,
            'email_verified_at' => now(),
        ]);

        $this->createClient($this->staff(), email: 'Client@Example.TEST');

        $this->assertDatabaseHas('clients', [
            'email' => self::EMAIL,
            'user_id' => $user->id,
        ]);
    }

    public function test_client_is_not_linked_to_unverified_user_even_when_phone_matches(): void
    {
        User::factory()->create([
            'email' => self::EMAIL,
            'email_verified_at' => null,
            'phone' => self::PHONE,
            'is_phone_verified' => true,
        ]);

        $this->createClient($this->staff());

        $this->assertDatabaseHas('clients', [
            'email' => self::EMAIL,
            'phone' => self::PHONE,
            'user_id' => null,
        ]);
    }

    public function test_clients_are_linked_when_email_gets_verified(): void
    {
        $user = User::factory()->create([
            'email' => self::EMAIL,
            'email_verified_at' => null,
        ]);
        $this->createClient($this->staff(), '+992900111221');
        $this->createClient($this->staff(), '+992900111222');

        Cache::put('otp:email:'.self::EMAIL, '123456', now()->addMinutes(5));
        $this->postJson('/api/v1/auth/verify-email', [
            'email' => 'Client@Example.TEST',
            'code' => '123456',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('clients', ['email' => self::EMAIL, 'user_id' => null]);
        $this->assertSame(2, DB::table('clients')->where('user_id', $user->id)->count());
    }
}
