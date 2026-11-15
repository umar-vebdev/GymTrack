<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Audit\Infrastructure\Persistence\AuditEntryModel;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ManageStaffEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_staff_and_audit_log_records_changes(): void
    {
        $owner = User::factory()->create([
            'name' => 'Owner',
            'email' => 'owner@example.test',
            'email_verified_at' => now(),
        ]);
        $worker = User::factory()->create([
            'name' => 'Worker',
            'email' => 'worker@example.test',
            'email_verified_at' => now(),
        ]);
        User::factory()->create([
            'name' => 'Unverified',
            'email' => 'unverified@example.test',
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($owner);
        $reg = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'Titan Co',
            'gym_name' => 'Titan Central',
        ])->assertCreated();
        $companyId = (int) $reg->json('data.company_id');

        // Неподтверждённый по email кандидат -> 400
        $this->postJson('/api/v1/business/staff', [
            'email' => 'unverified@example.test',
            'role' => 'admin',
        ])->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');

        // Добавление сотрудника
        $created = $this->postJson('/api/v1/business/staff', [
            'email' => 'Worker@Example.TEST',
            'role' => 'admin',
        ])->assertCreated()
            ->assertJsonPath('data.user_id', $worker->id)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.name', 'Worker')
            ->assertJsonPath('data.email', 'worker@example.test');

        $staffId = (int) $created->json('data.id');

        // Список сотрудников
        $this->getJson('/api/v1/business/staff')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Смена роли на trainer
        $this->patchJson("/api/v1/business/staff/{$staffId}", [
            'role' => 'trainer',
        ])->assertOk()
            ->assertJsonPath('data.role', 'trainer');

        // Попытка назначить owner -> 422
        $this->postJson('/api/v1/business/staff', [
            'email' => 'worker@example.test',
            'role' => 'owner',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Попытка удалить единственного владельца -> 400
        /** @var StaffMemberRepositoryInterface $repo */
        $repo = $this->app->make(StaffMemberRepositoryInterface::class);
        $ownerStaff = $repo->findByUserAndCompany($owner->id, $companyId);

        $this->deleteJson('/api/v1/business/staff/'.($ownerStaff?->id() ?? 0))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');

        // Удаление сотрудника
        $this->deleteJson("/api/v1/business/staff/{$staffId}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $actions = AuditEntryModel::query()
            ->where('company_id', $companyId)
            ->pluck('action')
            ->all();

        expect($actions)->toContain('STAFF_MEMBER_ADDED', 'STAFF_MEMBER_ROLE_CHANGED', 'STAFF_MEMBER_REMOVED');
    }

    public function test_non_owners_get_403_and_cross_tenant_staff_gets_404(): void
    {
        $ownerA = User::factory()->create(['email' => 'ownera@example.test', 'email_verified_at' => now()]);
        $ownerB = User::factory()->create(['email' => 'ownerb@example.test', 'email_verified_at' => now()]);
        $adminA = User::factory()->create(['email' => 'admina@example.test', 'email_verified_at' => now()]);
        $trainerA = User::factory()->create(['email' => 'trainera@example.test', 'email_verified_at' => now()]);
        $adminB = User::factory()->create(['email' => 'adminb@example.test', 'email_verified_at' => now()]);

        Sanctum::actingAs($ownerA);
        $this->postJson('/api/v1/business/companies', [
            'company_name' => 'A',
            'gym_name' => 'Gym A',
        ])->assertCreated();

        $this->postJson('/api/v1/business/staff', [
            'email' => 'admina@example.test',
            'role' => 'admin',
        ])->assertCreated();

        $this->postJson('/api/v1/business/staff', [
            'email' => 'trainera@example.test',
            'role' => 'trainer',
        ])->assertCreated();

        Sanctum::actingAs($ownerB);
        $this->postJson('/api/v1/business/companies', [
            'company_name' => 'B',
            'gym_name' => 'Gym B',
        ])->assertCreated();

        $staffBId = (int) $this->postJson('/api/v1/business/staff', [
            'email' => 'adminb@example.test',
            'role' => 'admin',
        ])->assertCreated()->json('data.id');

        // Администратор и тренер не имеют доступа к управлению сотрудниками
        Sanctum::actingAs($adminA);
        $this->getJson('/api/v1/business/staff')->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->postJson('/api/v1/business/staff', ['email' => 'adminb@example.test', 'role' => 'trainer'])->assertForbidden();

        Sanctum::actingAs($trainerA);
        $this->getJson('/api/v1/business/staff')->assertForbidden();

        // Владелец A не видит сотрудника компании B -> 404
        Sanctum::actingAs($ownerA);
        $this->patchJson("/api/v1/business/staff/{$staffBId}", ['role' => 'trainer'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
        $this->deleteJson("/api/v1/business/staff/{$staffBId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
