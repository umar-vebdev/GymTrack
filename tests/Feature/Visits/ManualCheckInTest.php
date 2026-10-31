<?php

declare(strict_types=1);

namespace Tests\Feature\Visits;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ManualCheckInTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, admin: User, companyId: int, gymId: int}
     */
    private function createCompanyAndGym(string $name = 'Gym Co'): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $company = CompanyModel::create([
            'owner_id' => $owner->id,
            'name' => $name,
        ]);

        StaffMemberModel::create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => Role::OWNER->value,
        ]);

        StaffMemberModel::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role' => Role::ADMIN->value,
        ]);

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($company->id, 'Main Hall', 'Dushanbe');

        return [
            'owner' => $owner,
            'admin' => $admin,
            'companyId' => $company->id,
            'gymId' => $gymId,
        ];
    }

    private function createClientWithMembership(int $companyId, int $visitsCount = 10): int
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'Rustam', '+992900111222');

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyId,
            'Visits 10',
            new Money(20000, 'TJS'),
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: $visitsCount),
        );

        /** @var MembershipRepositoryInterface $memberships */
        $memberships = $this->app->make(MembershipRepositoryInterface::class);
        $memberships->create($companyId, $clientId, $typeId, MembershipStatus::Active, $visitsCount, null, null);

        return $clientId;
    }

    public function test_admin_checks_in_client_manually_supports_backdating_and_idempotency(): void
    {
        $setup = $this->createCompanyAndGym();
        $clientId = $this->createClientWithMembership($setup['companyId'], 10);

        Sanctum::actingAs($setup['admin']);

        $backdated = (new DateTimeImmutable('-1 day'))->format('Y-m-d\T10:00:00\Z');

        $res = $this->postJson("/api/v1/business/clients/{$clientId}/visits", [
            'gym_id' => $setup['gymId'],
            'visited_at' => $backdated,
            'device' => 'Reception PC',
        ], [
            'Idempotency-Key' => 'manual-key-1',
        ]);

        $res->assertCreated()
            ->assertJsonPath('data.method', 'manual')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.remaining_visits', 9);

        $visitId = $res->json('data.id');

        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $setup['companyId'],
            'user_id' => $setup['admin']->id,
            'action' => 'VISIT_REGISTERED',
            'entity_type' => 'Visit',
            'entity_id' => (string) $visitId,
        ]);

        // Повтор с тем же Idempotency-Key не списывает второй раз
        $repeat = $this->postJson("/api/v1/business/clients/{$clientId}/visits", [
            'gym_id' => $setup['gymId'],
            'visited_at' => $backdated,
        ], [
            'Idempotency-Key' => 'manual-key-1',
        ]);

        $repeat->assertCreated()
            ->assertJsonPath('data.id', $visitId)
            ->assertJsonPath('data.remaining_visits', 9);
    }

    public function test_rejects_future_visited_at_and_isolates_companies(): void
    {
        $setupA = $this->createCompanyAndGym('Gym A');
        $setupB = $this->createCompanyAndGym('Gym B');

        $clientA = $this->createClientWithMembership($setupA['companyId'], 5);
        $clientB = $this->createClientWithMembership($setupB['companyId'], 5);

        Sanctum::actingAs($setupA['admin']);

        // Время в будущем запрещено
        $future = (new DateTimeImmutable('+1 day'))->format(DATE_ATOM);
        $this->postJson("/api/v1/business/clients/{$clientA}/visits", [
            'gym_id' => $setupA['gymId'],
            'visited_at' => $future,
        ])->assertStatus(400);

        // Чужой зал
        $this->postJson("/api/v1/business/clients/{$clientA}/visits", [
            'gym_id' => $setupB['gymId'],
        ])->assertNotFound();

        // Чужой клиент
        $this->postJson("/api/v1/business/clients/{$clientB}/visits", [
            'gym_id' => $setupA['gymId'],
        ])->assertStatus(400);
    }
}
