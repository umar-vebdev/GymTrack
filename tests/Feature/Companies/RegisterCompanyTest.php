<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegisterCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_company(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'GymTrack LLC',
            'gym_name' => 'Main Branch',
            'gym_address' => 'Dushanbe, 10th st.',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'company_id',
                    'gym_id',
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'owner_id' => $user->id,
            'name' => 'GymTrack LLC',
        ]);

        $this->assertDatabaseHas('staff_members', [
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $this->assertDatabaseHas('gyms', [
            'name' => 'Main Branch',
            'address' => 'Dushanbe, 10th st.',
        ]);
    }

    public function test_unauthenticated_user_cannot_register_company(): void
    {
        $response = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'GymTrack LLC',
            'gym_name' => 'Main Branch',
        ]);

        $response->assertStatus(401);
    }
}
