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
        $owner = User::factory()->create(['name' => 'Owner', 'phone' => '+992900200001', 'is_phone_verified' => true]);
        $worker = User::factory()->create(['name' => 'Worker', 'phone' => '+992900200002', 'is_phone_verified' => true]);

        Sanctum::actingAs($owner);
        $reg = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'Titan Co',
            'gym_name' => 'Titan Central',
        ])->assertCreated();
        $companyId = (int) $reg->json('data.company_id');

        // Добавление сотрудника
        $created = $this->postJson('/api/v1/business/staff', [
            'phone' => '+992900200002',
            'role' => 'admin',
        ])->assertCreated()
            ->assertJsonPath('data.user_id', $worker->id)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.name', 'Worker');

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
            'phone' => '+992900200002',
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
        $ownerA = User::factory()->create(['phone' => '+992900300001', 'is_phone_verified' => true]);
        $ownerB = User::factory()->create(['phone' => '+992900300002', 'is_phone_verified' => true]);
        $adminA = User::factory()->create(['phone' => '+992900300003', 'is_phone_verified' => true]);
        $trainerA = User::factory()->create(['phone' => '+992900300004', 'is_phone_verified' => true]);
        $adminB = User::factory()->create(['phone' => '+992900300005', 'is_phone_verified' => true]);

        Sanctum::actingAs($ownerA);
        $companyA = (int) $this->postJson('/api/v1/business/companies', [
            'company_name' => 'A',
            'gym_name' => 'Gym A',
        ])->assertCreated()->json('data.company_id');

        $this->postJson('/api/v1/business/staff', [
            'phone' => '+992900300003',
            'role' => 'admin',
        ])->assertCreated();

        $this->postJson('/api/v1/business/staff', [
            'phone' => '+992900300004',
            'role' => 'trainer',
        ])->assertCreated();

        Sanctum::actingAs($ownerB);
        $this->postJson('/api/v1/business/companies', [
            'company_name' => 'B',
            'gym_name' => 'Gym B',
        ])->assertCreated();

        $staffBId = (int) $this->postJson('/api/v1/business/staff', [
            'phone' => '+992900300005',
            'role' => 'admin',
        ])->assertCreated()->json('data.id');

        // Администратор и тренер не имеют доступа к управлению сотрудниками
        Sanctum::actingAs($adminA);
        $this->getJson('/api/v1/business/staff')->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->postJson('/api/v1/business/staff', ['phone' => '+992900300005', 'role' => 'trainer'])->assertForbidden();

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
