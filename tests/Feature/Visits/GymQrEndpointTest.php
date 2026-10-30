<?php

declare(strict_types=1);

namespace Tests\Feature\Visits;

use App\Models\User;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GymQrEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_gets_dynamic_qr_for_own_gym_and_cannot_access_foreign_gym(): void
    {
        $ownerA = User::factory()->create();
        $adminA = User::factory()->create();
        $ownerB = User::factory()->create();

        $companyA = CompanyModel::create(['owner_id' => $ownerA->id, 'name' => 'Gym A']);
        $companyB = CompanyModel::create(['owner_id' => $ownerB->id, 'name' => 'Gym B']);

        StaffMemberModel::create([
            'company_id' => $companyA->id,
            'user_id' => $adminA->id,
            'role' => Role::ADMIN->value,
        ]);

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymAId = $gyms->create($companyA->id, 'Центральный зал', 'Душанбе');
        $gymBId = $gyms->create($companyB->id, 'Чужой зал', 'Худжанд');

        Sanctum::actingAs($adminA);

        $res = $this->getJson("/api/v1/business/gyms/{$gymAId}/qr");

        $res->assertOk()
            ->assertJsonPath('data.gym_id', $gymAId)
            ->assertJsonPath('data.gym_name', 'Центральный зал')
            ->assertJsonPath('data.ttl_seconds', 60);

        $token = (string) $res->json('data.token');
        $this->assertStringStartsWith("{$gymAId}|", $token);

        // Зал другой компании недоступен (критерий 12.6)
        $this->getJson("/api/v1/business/gyms/{$gymBId}/qr")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'GYM_NOT_FOUND');
    }
}
