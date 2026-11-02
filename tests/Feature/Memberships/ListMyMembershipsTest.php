<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Memberships\Infrastructure\Persistence\MembershipModel;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ListMyMembershipsTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name): int
    {
        $owner = User::factory()->create();

        return CompanyModel::create(['owner_id' => $owner->id, 'name' => $name])->id;
    }

    private function createType(
        int $companyId,
        string $name,
        MembershipKind $kind,
        ?int $duration = null,
        ?int $visits = null,
        ?int $validity = null
    ): int {
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);

        $rules = MembershipRules::create(
            kind: $kind,
            startMode: StartMode::OnSale,
            durationDays: $kind === MembershipKind::Duration ? ($duration ?? 30) : null,
            visitsCount: $visits,
            validityDays: $validity ?? ($kind === MembershipKind::Visits ? 30 : null)
        );

        return $types->create($companyId, $name, new Money(10000, 'TJS'), $rules);
    }

    private function issueMembership(int $companyId, int $clientId, int $typeId): int
    {
        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);

        return $issuer->issue($companyId, $clientId, $typeId, new DateTimeImmutable('2026-11-01 10:00:00'));
    }

    public function test_unauthenticated_client_cannot_access_my_memberships(): void
    {
        $response = $this->getJson('/api/v1/client/memberships');

        $response->assertStatus(401);
    }

    public function test_client_without_cards_gets_empty_list(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/client/memberships');

        $response->assertStatus(200);
        $response->assertJson(['data' => []]);
    }

    public function test_client_gets_all_memberships_across_different_companies(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $companyA = $this->createCompany('Gym Alpha');
        $companyB = $this->createCompany('Gym Beta');

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientA = $clients->create($companyA, 'John', '+992900111111', $user->id);
        $clientB = $clients->create($companyB, 'John', '+992900111111', $user->id);

        $typeA = $this->createType($companyA, 'Alpha Monthly', MembershipKind::Duration, duration: 30);
        $typeB = $this->createType($companyB, 'Beta 10 Visits', MembershipKind::Visits, visits: 10);

        $memA = $this->issueMembership($companyA, $clientA, $typeA);
        $memB = $this->issueMembership($companyB, $clientB, $typeB);

        $response = $this->getJson('/api/v1/client/memberships');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $ids = array_column($data, 'id');
        $this->assertContains($memA, $ids);
        $this->assertContains($memB, $ids);

        $names = array_column($data, 'type_name');
        $this->assertContains('Alpha Monthly', $names);
        $this->assertContains('Beta 10 Visits', $names);
    }

    public function test_filter_by_status_active_and_past(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->createCompany('Gym One');
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $client = $clients->create($company, 'John', '+992900111111', $user->id);

        $type = $this->createType($company, 'Standard', MembershipKind::Duration);

        $activeMem = $this->issueMembership($company, $client, $type);
        $expiredMem = $this->issueMembership($company, $client, $type);

        // Manually mark one as expired
        MembershipModel::where('id', $expiredMem)->update(['status' => MembershipStatus::Expired->value]);

        // Filter active
        $resActive = $this->getJson('/api/v1/client/memberships?status=active');
        $resActive->assertStatus(200);
        $this->assertCount(1, $resActive->json('data'));
        $this->assertSame($activeMem, $resActive->json('data.0.id'));
        $this->assertTrue($resActive->json('data.0.is_usable'));

        // Filter past
        $resPast = $this->getJson('/api/v1/client/memberships?status=past');
        $resPast->assertStatus(200);
        $this->assertCount(1, $resPast->json('data'));
        $this->assertSame($expiredMem, $resPast->json('data.0.id'));
        $this->assertFalse($resPast->json('data.0.is_usable'));
    }

    public function test_filter_by_company_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $companyA = $this->createCompany('Gym A');
        $companyB = $this->createCompany('Gym B');

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientA = $clients->create($companyA, 'John', '+992900111111', $user->id);
        $clientB = $clients->create($companyB, 'John', '+992900111111', $user->id);

        $typeA = $this->createType($companyA, 'Type A', MembershipKind::Duration);
        $typeB = $this->createType($companyB, 'Type B', MembershipKind::Duration);

        $memA = $this->issueMembership($companyA, $clientA, $typeA);
        $this->issueMembership($companyB, $clientB, $typeB);

        $response = $this->getJson("/api/v1/client/memberships?company_id={$companyA}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($memA, $response->json('data.0.id'));
        $this->assertSame($companyA, $response->json('data.0.company_id'));
    }

    public function test_invalid_status_filter_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/client/memberships?status=invalid_status');

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }
}
