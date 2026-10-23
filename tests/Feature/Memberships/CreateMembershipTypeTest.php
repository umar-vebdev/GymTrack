<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\User;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateMembershipTypeTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/business/membership-types';

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'name' => '12 занятий',
            'price_minor' => 60000,
            'kind' => 'visits',
            'start_mode' => 'first_visit',
            'visits_count' => 12,
            'validity_days' => 60,
            'time_from' => '06:00',
            'time_to' => '17:00',
            'freeze_max_days' => 14,
            'freeze_max_times' => 2,
        ];
    }

    private function owner(): User
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'Co', 'gym_name' => 'Gym',
        ])->assertStatus(201);

        return $owner;
    }

    public function test_owner_creates_membership_type(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'sanctum')->postJson(self::URL, $this->validPayload())
            ->assertStatus(201)->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('membership_types', [
            'name' => '12 занятий',
            'kind' => 'visits',
            'price_minor' => 60000,
            'currency' => 'TJS',
            'visits_count' => 12,
            'time_from_minute' => 360,
            'time_to_minute' => 1020,
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_create_membership_type(): void
    {
        $owner = $this->owner();
        $admin = User::factory()->create();
        $companyId = (int) StaffMemberModel::query()->where('user_id', $owner->id)->value('company_id');
        StaffMemberModel::create(['user_id' => $admin->id, 'company_id' => $companyId, 'role' => Role::ADMIN->value]);

        $this->actingAs($admin, 'sanctum')->postJson(self::URL, $this->validPayload())->assertStatus(403);

        $this->assertDatabaseCount('membership_types', 0);
    }

    public function test_invalid_rules_return_422(): void
    {
        $owner = $this->owner();
        $payload = $this->validPayload();
        unset($payload['visits_count']);

        $this->actingAs($owner, 'sanctum')->postJson(self::URL, $payload)
            ->assertStatus(422)->assertJsonPath('error.code', 'INVALID_MEMBERSHIP_RULES');
    }

    public function test_invalid_shape_returns_422(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner, 'sanctum')->postJson(self::URL, [
            'name' => '', 'price_minor' => -1, 'kind' => 'bogus', 'start_mode' => 'on_sale',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_user_without_company_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson(self::URL, $this->validPayload())->assertStatus(403);
    }

    public function test_requires_auth(): void
    {
        $this->postJson(self::URL, [])->assertStatus(401);
    }
}
