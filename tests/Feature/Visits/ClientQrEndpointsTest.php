<?php

declare(strict_types=1);

namespace Tests\Feature\Visits;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ClientQrEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $trainer;

    private User $clientUser;

    private int $companyId;

    private int $gymId;

    private int $otherGymId;

    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->admin = User::factory()->create();
        $this->trainer = User::factory()->create();
        $this->clientUser = User::factory()->create(['phone' => '+992900333222', 'is_phone_verified' => true]);

        $this->companyId = CompanyModel::create(['owner_id' => $this->owner->id, 'name' => 'Titan Club'])->id;

        /** @var StaffMemberRepositoryInterface $staff */
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $staff->create($this->owner->id, $this->companyId, Role::OWNER);
        $staff->create($this->admin->id, $this->companyId, Role::ADMIN);
        $staff->create($this->trainer->id, $this->companyId, Role::TRAINER);

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $this->gymId = $gyms->create($this->companyId, 'Titan Center', 'ул. Рудаки, 25');

        $otherOwner = User::factory()->create();
        $otherCompanyId = CompanyModel::create(['owner_id' => $otherOwner->id, 'name' => 'Other Club'])->id;
        $this->otherGymId = $gyms->create($otherCompanyId, 'Foreign Gym', 'ул. Айни, 1');

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $this->clientId = $clients->create($this->companyId, 'Дамир', '+992900333222', $this->clientUser->id);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $this->companyId,
            '8 занятий',
            new Money(18000, 'TJS'),
            MembershipRules::create(
                kind: MembershipKind::Visits,
                startMode: StartMode::OnSale,
                visitsCount: 8,
                dailyLimit: 2,
            ),
        );

        /** @var MembershipRepositoryInterface $memberships */
        $memberships = $this->app->make(MembershipRepositoryInterface::class);
        $memberships->create($this->companyId, $this->clientId, $typeId, MembershipStatus::Active, 8, null, null);
    }

    public function test_client_gets_personal_qr_and_staff_scans_it_with_idempotency(): void
    {
        Sanctum::actingAs($this->clientUser);
        $qrResponse = $this->getJson('/api/v1/client/visits/qr')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['slot', 'token', 'ttl_seconds', 'expires_at'],
            ])
            ->assertJsonPath('data.ttl_seconds', 60);

        $token = (string) $qrResponse->json('data.token');

        // Администратор сканирует личный QR клиента на ресепшене
        Sanctum::actingAs($this->admin);
        $first = $this->postJson("/api/v1/business/gyms/{$this->gymId}/check-in-qr", [
            'token' => $token,
            'device' => 'Reception Tablet',
        ], [
            'Idempotency-Key' => 'scan-qr-1',
        ]);

        $first->assertCreated()
            ->assertJsonPath('data.gym_id', $this->gymId)
            ->assertJsonPath('data.client_id', $this->clientId)
            ->assertJsonPath('data.method', 'client_qr')
            ->assertJsonPath('data.remaining_visits', 7);

        // Идемпотентный повтор не списывает повторно
        $this->postJson("/api/v1/business/gyms/{$this->gymId}/check-in-qr", [
            'token' => $token,
        ], [
            'Idempotency-Key' => 'scan-qr-1',
        ])->assertCreated()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.remaining_visits', 7);

        // Владелец тоже имеет право на сканирование (без ключа -> второе списание при лимите 2)
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/business/gyms/{$this->gymId}/check-in-qr", [
            'token' => $token,
        ])->assertCreated()
            ->assertJsonPath('data.remaining_visits', 6);
    }

    public function test_role_restrictions_tenant_isolation_and_invalid_token(): void
    {
        Sanctum::actingAs($this->clientUser);
        $token = (string) $this->getJson('/api/v1/client/visits/qr')->json('data.token');

        // Тренер и посторонний клиент не имеют доступа -> 403 FORBIDDEN
        Sanctum::actingAs($this->trainer);
        $this->postJson("/api/v1/business/gyms/{$this->gymId}/check-in-qr", ['token' => $token])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        Sanctum::actingAs($this->clientUser);
        $this->postJson("/api/v1/business/gyms/{$this->gymId}/check-in-qr", ['token' => $token])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Чужой зал -> 404 GYM_NOT_FOUND
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/v1/business/gyms/{$this->otherGymId}/check-in-qr", ['token' => $token])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'GYM_NOT_FOUND');

        // Битый токен -> 422 INVALID_QR_TOKEN
        $this->postJson("/api/v1/business/gyms/{$this->gymId}/check-in-qr", ['token' => 'client|999|100|bad'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_QR_TOKEN');
    }
}
