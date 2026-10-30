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

final class CancelVisitTest extends TestCase
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

    /**
     * @return array{clientId: int, membershipId: int}
     */
    private function createClientWithMembership(int $companyId, int $visitsCount = 1): array
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'Rustam', '+992900111222');

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyId,
            'Single Pack',
            new Money(5000, 'TJS'),
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: $visitsCount),
        );

        /** @var MembershipRepositoryInterface $memberships */
        $memberships = $this->app->make(MembershipRepositoryInterface::class);
        $membershipId = $memberships->create($companyId, $clientId, $typeId, MembershipStatus::Active, $visitsCount, null, null);

        return ['clientId' => $clientId, 'membershipId' => $membershipId];
    }

    public function test_staff_cancels_visit_within_24h_restores_visit_and_writes_audit(): void
    {
        $setup = $this->createCompanyAndGym();
        $pair = $this->createClientWithMembership($setup['companyId'], 1);

        Sanctum::actingAs($setup['admin']);

        $checkIn = $this->postJson("/api/v1/business/clients/{$pair['clientId']}/visits", [
            'gym_id' => $setup['gymId'],
        ])->assertCreated()
            ->assertJsonPath('data.remaining_visits', 0);

        $visitId = $checkIn->json('data.id');

        $cancel = $this->postJson("/api/v1/business/visits/{$visitId}/cancel", [
            'reason' => 'Ошибочно выбрали однофамильца',
        ]);

        $cancel->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancel_reason', 'Ошибочно выбрали однофамильца')
            ->assertJsonPath('data.cancelled_by_user_id', $setup['admin']->id);

        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);
        $restored = $repo->findById($pair['membershipId']);
        $this->assertNotNull($restored);
        $this->assertSame(1, $restored->remainingVisits());
        $this->assertSame(MembershipStatus::Active, $restored->status());

        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $setup['companyId'],
            'user_id' => $setup['admin']->id,
            'action' => 'VISIT_CANCELLED',
            'entity_type' => 'Visit',
            'entity_id' => (string) $visitId,
        ]);
    }

    public function test_cannot_cancel_visit_older_than_24_hours_or_from_other_company(): void
    {
        $setupA = $this->createCompanyAndGym('Gym A');
        $setupB = $this->createCompanyAndGym('Gym B');
        $pairA = $this->createClientWithMembership($setupA['companyId'], 5);

        Sanctum::actingAs($setupA['admin']);

        // Отметка 25 часов назад
        $over24hAgo = (new DateTimeImmutable('-25 hours'))->format(DATE_ATOM);
        $oldVisit = $this->postJson("/api/v1/business/clients/{$pairA['clientId']}/visits", [
            'gym_id' => $setupA['gymId'],
            'visited_at' => $over24hAgo,
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/business/visits/{$oldVisit}/cancel", [
            'reason' => 'Слишком поздно',
        ])->assertStatus(400);

        // Свежая отметка, но пытается отменить сотрудник другой компании
        $freshVisit = $this->postJson("/api/v1/business/clients/{$pairA['clientId']}/visits", [
            'gym_id' => $setupA['gymId'],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($setupB['admin']);
        $this->postJson("/api/v1/business/visits/{$freshVisit}/cancel", [
            'reason' => 'Чужая компания',
        ])->assertStatus(400);
    }
}

