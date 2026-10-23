<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateClientTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithCompany(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'Co',
            'gym_name' => 'Gym',
        ])->assertStatus(201);

        return $user;
    }

    public function test_staff_can_create_client(): void
    {
        $user = $this->ownerWithCompany();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/business/clients', [
            'name' => 'John Doe',
            'phone' => '+992900111222',
        ])->assertStatus(201)->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('clients', ['name' => 'John Doe', 'phone' => '+992900111222']);
    }

    public function test_duplicate_phone_in_same_company_is_rejected(): void
    {
        $user = $this->ownerWithCompany();
        $payload = ['name' => 'John', 'phone' => '+992900111222'];

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/business/clients', $payload)->assertStatus(201);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/business/clients', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CLIENT_PHONE_EXISTS');
    }

    public function test_same_phone_is_allowed_in_another_company(): void
    {
        $first = $this->ownerWithCompany();
        $this->actingAs($first, 'sanctum')->postJson('/api/v1/business/clients', [
            'name' => 'A', 'phone' => '+992900111222',
        ])->assertStatus(201);

        $second = $this->ownerWithCompany();
        $this->actingAs($second, 'sanctum')->postJson('/api/v1/business/clients', [
            'name' => 'B', 'phone' => '+992900111222',
        ])->assertStatus(201);
    }

    public function test_user_without_company_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/business/clients', [
            'name' => 'John', 'phone' => '+992900111222',
        ])->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/business/clients', [])->assertStatus(401);
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $user = $this->ownerWithCompany();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/business/clients', ['name' => '', 'phone' => 'abc'])
            ->assertStatus(422);
    }
}
