<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_currencies_or_update_currency(): void
    {
        $this->getJson('/api/v1/business/currencies')->assertStatus(401);

        $this->patchJson('/api/v1/business/company/currency', [
            'currency_id' => 2,
        ])->assertStatus(401);
    }

    public function test_authenticated_user_can_list_currencies(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/business/currencies');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'symbol',
                    ],
                ],
            ]);

        $response->assertJsonFragment([
            'id' => 1,
            'code' => 'TJS',
            'name' => 'Таджикский сомони',
        ]);
    }

    public function test_owner_can_update_company_currency(): void
    {
        $owner = User::factory()->create();

        /** @var CompanyRepositoryInterface $companyRepo */
        $companyRepo = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companyRepo->create($owner->id, 'GymTrack Test');

        /** @var StaffMemberRepositoryInterface $staffRepo */
        $staffRepo = $this->app->make(StaffMemberRepositoryInterface::class);
        $staffRepo->create($owner->id, $companyId, Role::OWNER);

        $response = $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/business/company/currency', [
            'currency_id' => 2, // RUB
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'company_id' => $companyId,
                    'currency_id' => 2,
                    'currency_code' => 'RUB',
                ],
            ]);

        $this->assertDatabaseHas('companies', [
            'id' => $companyId,
            'currency_id' => 2,
        ]);
    }

    public function test_non_owner_cannot_update_company_currency(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        /** @var CompanyRepositoryInterface $companyRepo */
        $companyRepo = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companyRepo->create($owner->id, 'GymTrack Test');

        /** @var StaffMemberRepositoryInterface $staffRepo */
        $staffRepo = $this->app->make(StaffMemberRepositoryInterface::class);
        $staffRepo->create($owner->id, $companyId, Role::OWNER);
        $staffRepo->create($admin->id, $companyId, Role::ADMIN);

        $response = $this->actingAs($admin, 'sanctum')->patchJson('/api/v1/business/company/currency', [
            'currency_id' => 2,
        ]);

        $response->assertStatus(403);
    }

    public function test_update_company_currency_validates_existing_currency_id(): void
    {
        $owner = User::factory()->create();

        /** @var CompanyRepositoryInterface $companyRepo */
        $companyRepo = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companyRepo->create($owner->id, 'GymTrack Test');

        /** @var StaffMemberRepositoryInterface $staffRepo */
        $staffRepo = $this->app->make(StaffMemberRepositoryInterface::class);
        $staffRepo->create($owner->id, $companyId, Role::OWNER);

        $response = $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/business/company/currency', [
            'currency_id' => 9999,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertArrayHasKey('currency_id', $response->json('error.details'));
    }
}
