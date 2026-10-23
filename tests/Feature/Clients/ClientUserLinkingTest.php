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

    private const PHONE = '+992900111222';

    private function staff(): User
    {
        $staff = User::factory()->create();
        $this->actingAs($staff, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'Co', 'gym_name' => 'Gym',
        ])->assertStatus(201);

        return $staff;
    }

    private function createClient(User $staff): void
    {
        $this->actingAs($staff, 'sanctum')->postJson('/api/v1/business/clients', [
            'name' => 'John', 'phone' => self::PHONE,
        ])->assertStatus(201);
    }

    public function test_client_is_linked_on_creation_if_verified_user_exists(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'is_phone_verified' => true]);

        $this->createClient($this->staff());

        $this->assertDatabaseHas('clients', ['phone' => self::PHONE, 'user_id' => $user->id]);
    }

    public function test_client_is_not_linked_to_unverified_user(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'is_phone_verified' => false]);

        $this->createClient($this->staff());

        $this->assertDatabaseHas('clients', ['phone' => self::PHONE, 'user_id' => null]);
    }

    public function test_clients_are_linked_when_phone_gets_verified(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'is_phone_verified' => false]);
        $this->createClient($this->staff());
        $this->createClient($this->staff());

        Cache::put('otp:'.self::PHONE, '123456');
        $this->postJson('/api/v1/auth/verify-phone', ['phone' => self::PHONE, 'code' => '123456'])
            ->assertStatus(200);

        $this->assertDatabaseMissing('clients', ['phone' => self::PHONE, 'user_id' => null]);
        $this->assertSame(2, DB::table('clients')->where('user_id', $user->id)->count());
    }
}
