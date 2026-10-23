<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\User;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListAndToggleMembershipTypesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'Test Co',
            'gym_name' => 'Main Gym',
        ])->assertStatus(201);

        return $owner;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createType(User $owner, string $name, string $kind = 'single', array $extra = []): int
    {
        $payload = array_merge([
            'name' => $name,
            'price_minor' => 1000,
            'kind' => $kind,
            'start_mode' => 'on_sale',
        ], $extra);

        $res = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/business/membership-types', $payload)
            ->assertStatus(201);

        return (int) $res->json('data.id');
    }

    public function test_staff_can_list_membership_types(): void
    {
        $owner = $this->owner();
        $id1 = $this->createType($owner, 'Single Pass');
        $id2 = $this->createType($owner, '10 Visits', 'visits', ['visits_count' => 10]);

        $res = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/business/membership-types')
            ->assertOk();

        $this->assertCount(2, $res->json('data'));
    }

    public function test_owner_can_toggle_membership_type_active_status(): void
    {
        $owner = $this->owner();
        $id = $this->createType($owner, 'Single Pass');

        // Deactivate
        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/business/membership-types/{$id}/active", ['is_active' => false])
            ->assertOk();

        $this->assertDatabaseHas('membership_types', [
            'id' => $id,
            'is_active' => false,
        ]);

        // List only active
        $res = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/business/membership-types?only_active=1')
            ->assertOk();

        $this->assertCount(0, $res->json('data'));

        // List all
        $resAll = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/business/membership-types')
            ->assertOk();

        $this->assertCount(1, $resAll->json('data'));
    }

    public function test_admin_cannot_toggle_active_status(): void
    {
        $owner = $this->owner();
        $id = $this->createType($owner, 'Single Pass');

        $admin = User::factory()->create();
        $companyId = (int) StaffMemberModel::query()->where('user_id', $owner->id)->value('company_id');
        StaffMemberModel::create([
            'user_id' => $admin->id,
            'company_id' => $companyId,
            'role' => Role::ADMIN->value,
        ]);

        // Admin can list
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/business/membership-types')
            ->assertOk();

        // Admin cannot toggle active
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/business/membership-types/{$id}/active", ['is_active' => false])
            ->assertStatus(403);
    }

    public function test_cannot_toggle_type_of_another_company(): void
    {
        $owner1 = $this->owner();
        $id1 = $this->createType($owner1, 'Company 1 Pass');

        $owner2 = $this->owner();

        $this->actingAs($owner2, 'sanctum')
            ->patchJson("/api/v1/business/membership-types/{$id1}/active", ['is_active' => false])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'MEMBERSHIP_TYPE_NOT_FOUND');
    }
}
