<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ManageGymsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_create_and_update_company_gyms(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $reg = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'Olympus',
            'gym_name' => 'Центральный',
            'gym_address' => 'ул. Рудаки 1',
        ])->assertCreated();

        $firstGymId = (int) $reg->json('data.gym_id');
        $companyId = (int) $reg->json('data.company_id');

        // Создание второго филиала с кастомным часовым поясом
        $created = $this->postJson('/api/v1/business/gyms', [
            'name' => 'Филиал Восток',
            'address' => 'ул. Сомони 5',
            'timezone' => 'Asia/Almaty',
        ])->assertCreated()
            ->assertJsonPath('data.company_id', $companyId)
            ->assertJsonPath('data.name', 'Филиал Восток')
            ->assertJsonPath('data.timezone', 'Asia/Almaty');

        $secondGymId = (int) $created->json('data.id');

        // Список залов
        $this->getJson('/api/v1/business/gyms')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $firstGymId)
            ->assertJsonPath('data.0.timezone', 'Asia/Dushanbe')
            ->assertJsonPath('data.1.id', $secondGymId)
            ->assertJsonPath('data.1.timezone', 'Asia/Almaty');

        // Обновление филиала
        $this->patchJson("/api/v1/business/gyms/{$secondGymId}", [
            'name' => 'Филиал Восток Pro',
            'address' => null,
            'timezone' => 'Europe/Moscow',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Филиал Восток Pro')
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.timezone', 'Europe/Moscow');

        // Невалидный часовой пояс -> 422
        $this->postJson('/api/v1/business/gyms', [
            'name' => 'Bad Gym',
            'timezone' => 'Invalid/Zone',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_role_restrictions_and_tenant_isolation_for_gyms(): void
    {
        $ownerA = User::factory()->create();
        Sanctum::actingAs($ownerA);
        $regA = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'Company A',
            'gym_name' => 'Gym A',
        ])->assertCreated();
        $companyA = (int) $regA->json('data.company_id');

        $ownerB = User::factory()->create();
        Sanctum::actingAs($ownerB);
        $regB = $this->postJson('/api/v1/business/companies', [
            'company_name' => 'Company B',
            'gym_name' => 'Gym B',
        ])->assertCreated();
        $gymB = (int) $regB->json('data.gym_id');

        // Администратор может смотреть список, но не может создавать/обновлять
        $adminA = User::factory()->create();
        /** @var StaffMemberRepositoryInterface $staff */
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $staff->create($adminA->id, $companyA, Role::ADMIN);

        Sanctum::actingAs($adminA);
        $this->getJson('/api/v1/business/gyms')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/business/gyms', ['name' => 'New Gym'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
        $this->patchJson("/api/v1/business/gyms/{$gymB}", ['name' => 'Hack'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Тренер не имеет доступа ни к списку, ни к изменению
        $trainerA = User::factory()->create();
        $staff->create($trainerA->id, $companyA, Role::TRAINER);
        Sanctum::actingAs($trainerA);
        $this->getJson('/api/v1/business/gyms')->assertForbidden();

        // Владелец A не может обновить зал компании B -> 404
        Sanctum::actingAs($ownerA);
        $this->patchJson("/api/v1/business/gyms/{$gymB}", [
            'name' => 'Cross Tenant Hack',
        ])->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}

