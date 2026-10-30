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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ListVisitsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{admin: User, companyId: int, gymId: int}
     */
    private function createCompanyAndGym(string $name = 'Gym Co'): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $company = CompanyModel::create(['owner_id' => $owner->id, 'name' => $name]);

        StaffMemberModel::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role' => Role::ADMIN->value,
        ]);

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($company->id, 'Hall', 'Dushanbe');

        return ['admin' => $admin, 'companyId' => $company->id, 'gymId' => $gymId];
    }

    private function createClientWithMembership(int $companyId, string $phone): int
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'Client', $phone);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyId,
            'Visits 5',
            new Money(10000, 'TJS'),
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 5),
        );

        /** @var MembershipRepositoryInterface $memberships */
        $memberships = $this->app->make(MembershipRepositoryInterface::class);
        $memberships->create($companyId, $clientId, $typeId, MembershipStatus::Active, 5, null, null);

        return $clientId;
    }

    public function test_staff_lists_company_and_client_visits_with_tenant_isolation(): void
    {
        $setupA = $this->createCompanyAndGym('Gym A');
        $setupB = $this->createCompanyAndGym('Gym B');

        $clientA1 = $this->createClientWithMembership($setupA['companyId'], '+992900111001');
        $clientA2 = $this->createClientWithMembership($setupA['companyId'], '+992900111002');
        $clientB1 = $this->createClientWithMembership($setupB['companyId'], '+992900222001');

        Sanctum::actingAs($setupA['admin']);
        $this->postJson("/api/v1/business/clients/{$clientA1}/visits", ['gym_id' => $setupA['gymId']])->assertCreated();
        $this->postJson("/api/v1/business/clients/{$clientA2}/visits", ['gym_id' => $setupA['gymId']])->assertCreated();

        Sanctum::actingAs($setupB['admin']);
        $this->postJson("/api/v1/business/clients/{$clientB1}/visits", ['gym_id' => $setupB['gymId']])->assertCreated();

        Sanctum::actingAs($setupA['admin']);

        $this->getJson('/api/v1/business/visits')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/business/clients/{$clientA1}/visits")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.client_id', $clientA1);

        // Клиент другой компании недоступен
        $this->getJson("/api/v1/business/clients/{$clientB1}/visits")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CLIENT_NOT_FOUND');
    }
}
