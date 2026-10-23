<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Models\User;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MergeClientsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'Co', 'gym_name' => 'Gym',
        ])->assertStatus(201);

        return $owner;
    }

    private function add(User $staff, string $phone): int
    {
        return (int) $this->actingAs($staff, 'sanctum')->postJson('/api/v1/business/clients', [
            'name' => 'C'.$phone, 'phone' => $phone,
        ])->assertStatus(201)->json('data.id');
    }

    public function test_owner_merges_duplicates(): void
    {
        $owner = $this->owner();
        $source = $this->add($owner, '+992900000001');
        $target = $this->add($owner, '+992900000002');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/business/clients/merge', [
            'source_id' => $source, 'target_id' => $target,
        ])->assertOk();

        $this->assertDatabaseMissing('clients', ['id' => $source]);
        $this->assertDatabaseHas('clients', ['id' => $target]);
    }

    public function test_account_of_source_moves_to_target(): void
    {
        $owner = $this->owner();
        $account = User::factory()->create(['phone' => '+992900000001', 'is_phone_verified' => true]);
        $source = $this->add($owner, '+992900000001');
        $target = $this->add($owner, '+992900000002');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/business/clients/merge', [
            'source_id' => $source, 'target_id' => $target,
        ])->assertOk();

        $this->assertDatabaseHas('clients', ['id' => $target, 'user_id' => $account->id]);
    }

    public function test_cannot_merge_with_itself(): void
    {
        $owner = $this->owner();
        $id = $this->add($owner, '+992900000001');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/business/clients/merge', [
            'source_id' => $id, 'target_id' => $id,
        ])->assertStatus(422);
    }

    public function test_cannot_merge_clients_of_other_company(): void
    {
        $owner = $this->owner();
        $mine = $this->add($owner, '+992900000001');
        $other = $this->owner();
        $foreign = $this->add($other, '+992900000002');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/business/clients/merge', [
            'source_id' => $foreign, 'target_id' => $mine,
        ])->assertStatus(404)->assertJsonPath('error.code', 'CLIENT_NOT_FOUND');

        $this->assertDatabaseHas('clients', ['id' => $foreign]);
    }

    public function test_admin_cannot_merge(): void
    {
        $owner = $this->owner();
        $a = $this->add($owner, '+992900000001');
        $b = $this->add($owner, '+992900000002');
        $admin = User::factory()->create();
        $companyId = (int) StaffMemberModel::query()->where('user_id', $owner->id)->value('company_id');
        StaffMemberModel::create(['user_id' => $admin->id, 'company_id' => $companyId, 'role' => Role::ADMIN->value]);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/business/clients/merge', [
            'source_id' => $a, 'target_id' => $b,
        ])->assertStatus(403);

        $this->assertDatabaseHas('clients', ['id' => $a]);
    }

    public function test_requires_auth(): void
    {
        $this->postJson('/api/v1/business/clients/merge', [])->assertStatus(401);
    }
}
