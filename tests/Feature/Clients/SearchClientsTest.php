<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SearchClientsTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $staff = User::factory()->create();
        $this->actingAs($staff, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'Co', 'gym_name' => 'Gym',
        ])->assertStatus(201);

        return $staff;
    }

    private function add(User $staff, string $name, string $phone): void
    {
        $this->actingAs($staff, 'sanctum')->postJson('/api/v1/business/clients', [
            'name' => $name, 'phone' => $phone,
        ])->assertStatus(201);
    }

    public function test_it_finds_client_by_part_of_phone(): void
    {
        $staff = $this->staff();
        $this->add($staff, 'Alice', '+992900111222');
        $this->add($staff, 'Bob', '+992933444555');

        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/business/clients?search=111')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alice')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_finds_client_by_name_case_insensitive_and_lists_all(): void
    {
        $staff = $this->staff();
        $this->add($staff, 'Alice', '+992900111222');
        $this->add($staff, 'Bob', '+992933444555');

        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/business/clients?search=bOB')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bob');

        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/business/clients')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_it_paginates(): void
    {
        $staff = $this->staff();
        $this->add($staff, 'A', '+992900000001');
        $this->add($staff, 'B', '+992900000002');
        $this->add($staff, 'C', '+992900000003');

        $this->actingAs($staff, 'sanctum')->getJson('/api/v1/business/clients?per_page=2&page=2')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 3);
    }

    public function test_it_does_not_leak_clients_of_other_company(): void
    {
        $first = $this->staff();
        $this->add($first, 'Secret', '+992900111222');
        $second = $this->staff();

        $this->actingAs($second, 'sanctum')->getJson('/api/v1/business/clients')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_auth_and_forbidden(): void
    {
        $this->getJson('/api/v1/business/clients')->assertStatus(401);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/v1/business/clients')->assertStatus(403);
    }
}
