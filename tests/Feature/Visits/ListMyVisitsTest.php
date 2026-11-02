<?php

declare(strict_types=1);

namespace Tests\Feature\Visits;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ListMyVisitsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{companyId: int, gymId: int}
     */
    private function createCompanyAndGym(string $name): array
    {
        $owner = User::factory()->create();
        $company = CompanyModel::create(['owner_id' => $owner->id, 'name' => $name]);

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($company->id, $name.' Gym', 'Dushanbe');

        return [
            'companyId' => $company->id,
            'gymId' => $gymId,
        ];
    }

    /**
     * @return array{clientId: int, membershipId: int}
     */
    private function createClientWithMembership(int $companyId, int $userId): array
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'Test Client', '+992900111222', $userId);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyId,
            'Standard',
            new Money(10000, 'TJS'),
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 10)
        );

        /** @var MembershipRepositoryInterface $memberships */
        $memberships = $this->app->make(MembershipRepositoryInterface::class);
        $membershipId = $memberships->create($companyId, $clientId, $typeId, MembershipStatus::Active, 10, null, null);

        return [
            'clientId' => $clientId,
            'membershipId' => $membershipId,
        ];
    }

    public function test_unauthenticated_client_cannot_access_my_visits(): void
    {
        $response = $this->getJson('/api/v1/client/visits');

        $response->assertStatus(401);
    }

    public function test_client_without_cards_gets_empty_list_with_meta(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/client/visits');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [],
            'meta' => [
                'page' => 1,
                'per_page' => 20,
                'total' => 0,
            ],
        ]);
    }

    public function test_client_gets_all_visits_across_different_companies(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $companyA = $this->createCompanyAndGym('Alpha');
        $companyB = $this->createCompanyAndGym('Beta');

        $cardA = $this->createClientWithMembership($companyA['companyId'], $user->id);
        $cardB = $this->createClientWithMembership($companyB['companyId'], $user->id);

        /** @var VisitRepositoryInterface $visits */
        $visits = $this->app->make(VisitRepositoryInterface::class);

        $visits->create(
            companyId: $companyA['companyId'],
            gymId: $companyA['gymId'],
            clientId: $cardA['clientId'],
            membershipId: $cardA['membershipId'],
            method: VisitMethod::Qr,
            visitedAt: new DateTimeImmutable('2026-11-01 10:00:00'),
            registeredByUserId: null
        );

        $visits->create(
            companyId: $companyB['companyId'],
            gymId: $companyB['gymId'],
            clientId: $cardB['clientId'],
            membershipId: $cardB['membershipId'],
            method: VisitMethod::Manual,
            visitedAt: new DateTimeImmutable('2026-11-02 11:00:00'),
            registeredByUserId: null
        );

        $response = $this->getJson('/api/v1/client/visits');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 2);
        // Latest first
        $response->assertJsonPath('data.0.company_id', $companyB['companyId']);
        $response->assertJsonPath('data.0.method', 'manual');
        $response->assertJsonPath('data.1.company_id', $companyA['companyId']);
        $response->assertJsonPath('data.1.method', 'qr');
    }

    public function test_client_can_filter_visits_by_company(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $companyA = $this->createCompanyAndGym('Alpha');
        $companyB = $this->createCompanyAndGym('Beta');

        $cardA = $this->createClientWithMembership($companyA['companyId'], $user->id);
        $cardB = $this->createClientWithMembership($companyB['companyId'], $user->id);

        /** @var VisitRepositoryInterface $visits */
        $visits = $this->app->make(VisitRepositoryInterface::class);

        $visits->create(
            companyId: $companyA['companyId'],
            gymId: $companyA['gymId'],
            clientId: $cardA['clientId'],
            membershipId: $cardA['membershipId'],
            method: VisitMethod::Qr,
            visitedAt: new DateTimeImmutable('2026-11-01 10:00:00'),
            registeredByUserId: null
        );

        $visits->create(
            companyId: $companyB['companyId'],
            gymId: $companyB['gymId'],
            clientId: $cardB['clientId'],
            membershipId: $cardB['membershipId'],
            method: VisitMethod::Manual,
            visitedAt: new DateTimeImmutable('2026-11-02 11:00:00'),
            registeredByUserId: null
        );

        $response = $this->getJson('/api/v1/client/visits?company_id='.$companyA['companyId']);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.company_id', $companyA['companyId']);
    }

    public function test_client_can_filter_visits_by_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->createCompanyAndGym('Gym Alpha');
        $card = $this->createClientWithMembership($company['companyId'], $user->id);

        /** @var VisitRepositoryInterface $visits */
        $visits = $this->app->make(VisitRepositoryInterface::class);

        $visit1 = $visits->create(
            companyId: $company['companyId'],
            gymId: $company['gymId'],
            clientId: $card['clientId'],
            membershipId: $card['membershipId'],
            method: VisitMethod::Qr,
            visitedAt: new DateTimeImmutable('2026-11-01 10:00:00'),
            registeredByUserId: null
        );

        $visit2 = $visits->create(
            companyId: $company['companyId'],
            gymId: $company['gymId'],
            clientId: $card['clientId'],
            membershipId: $card['membershipId'],
            method: VisitMethod::Manual,
            visitedAt: new DateTimeImmutable('2026-11-02 10:00:00'),
            registeredByUserId: null
        );

        $admin = User::factory()->create();

        // Cancel visit 2
        $visit2->cancel('Mistake', $admin->id, new DateTimeImmutable('2026-11-02 10:30:00'));
        $visits->save($visit2);

        $responseCompleted = $this->getJson('/api/v1/client/visits?status=completed');
        $responseCompleted->assertStatus(200);
        $responseCompleted->assertJsonCount(1, 'data');
        $responseCompleted->assertJsonPath('data.0.id', $visit1->id());
        $responseCompleted->assertJsonPath('data.0.status', 'completed');

        $responseCancelled = $this->getJson('/api/v1/client/visits?status=cancelled');
        $responseCancelled->assertStatus(200);
        $responseCancelled->assertJsonCount(1, 'data');
        $responseCancelled->assertJsonPath('data.0.id', $visit2->id());
        $responseCancelled->assertJsonPath('data.0.status', 'cancelled');
        $responseCancelled->assertJsonPath('data.0.cancel_reason', 'Mistake');
    }

    public function test_client_can_paginate_visits(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->createCompanyAndGym('Gym Alpha');
        $card = $this->createClientWithMembership($company['companyId'], $user->id);

        /** @var VisitRepositoryInterface $visits */
        $visits = $this->app->make(VisitRepositoryInterface::class);

        for ($i = 1; $i <= 5; $i++) {
            $visits->create(
                companyId: $company['companyId'],
                gymId: $company['gymId'],
                clientId: $card['clientId'],
                membershipId: $card['membershipId'],
                method: VisitMethod::Qr,
                visitedAt: new DateTimeImmutable("2026-11-0{$i} 10:00:00"),
                registeredByUserId: null
            );
        }

        $response = $this->getJson('/api/v1/client/visits?page=2&per_page=2');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.page', 2);
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_invalid_query_parameters_return_validation_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $responseStatus = $this->getJson('/api/v1/client/visits?status=unknown');
        $responseStatus->assertStatus(422);
        $responseStatus->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertArrayHasKey('status', $responseStatus->json('error.details'));

        $responsePerPage = $this->getJson('/api/v1/client/visits?per_page=200');
        $responsePerPage->assertStatus(422);
        $responsePerPage->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertArrayHasKey('per_page', $responsePerPage->json('error.details'));
    }
}
