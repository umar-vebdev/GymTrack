<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Memberships\Application\Commands\CreateMembershipTypeCommand;
use App\Modules\Memberships\Application\CreateMembershipType;
use App\Modules\Memberships\Application\SetMembershipTypeActive;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Sales\Domain\Events\MembershipRequestCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class ClientMembershipRequestsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{int, int, int}
     */
    private function setupGymAndMembershipType(): array
    {
        $owner = User::factory()->create();

        /** @var CompanyRepositoryInterface $companies */
        $companies = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companies->create($owner->id, 'Olympus Gym');

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Central Branch', 'Rudaki ave 10');

        /** @var CreateMembershipType $createType */
        $createType = $this->app->make(CreateMembershipType::class);
        $typeId = $createType->execute(new CreateMembershipTypeCommand(
            companyId: $companyId,
            name: '12 Visits Package',
            priceMinor: 60000,
            currency: 'TJS',
            kind: MembershipKind::Visits,
            startMode: StartMode::FirstVisit,
            visitsCount: 12,
            durationDays: null,
            validityDays: 60,
            dailyLimit: 1,
            timeFromMinute: null,
            timeToMinute: null,
            freezeMaxDays: 0,
            freezeMaxTimes: 0
        ));

        return [$companyId, $gymId, $typeId];
    }

    public function test_guest_cannot_access_membership_requests(): void
    {
        $this->postJson('/api/v1/client/membership-requests', [])->assertStatus(401);
        $this->getJson('/api/v1/client/membership-requests')->assertStatus(401);
    }

    public function test_client_creates_membership_request_successfully(): void
    {
        Event::fake([MembershipRequestCreated::class]);

        [$companyId, $gymId, $typeId] = $this->setupGymAndMembershipType();

        $clientUser = User::factory()->create([
            'name' => 'Faridun Olimov',
            'phone' => '+992900000001',
        ]);

        $response = $this->actingAs($clientUser, 'sanctum')->postJson('/api/v1/client/membership-requests', [
            'gym_id' => $gymId,
            'membership_type_id' => $typeId,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'company_id',
                    'gym_id',
                    'user_id',
                    'client_id',
                    'membership_type_id',
                    'status',
                    'expires_at',
                    'created_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'company_id' => $companyId,
                    'gym_id' => $gymId,
                    'user_id' => $clientUser->id,
                    'membership_type_id' => $typeId,
                    'status' => 'pending',
                ],
            ]);

        $requestId = (int) $response->json('data.id');
        $clientId = (int) $response->json('data.client_id');

        // Проверяем, что карточка клиента автоматически создана в CRM зала
        $this->assertDatabaseHas('clients', [
            'id' => $clientId,
            'company_id' => $companyId,
            'user_id' => $clientUser->id,
            'name' => 'Faridun Olimov',
            'phone' => '+992900000001',
        ]);

        $this->assertDatabaseHas('sales_membership_requests', [
            'id' => $requestId,
            'status' => 'pending',
            'client_id' => $clientId,
        ]);

        Event::assertDispatched(MembershipRequestCreated::class, function (MembershipRequestCreated $e) use ($requestId, $companyId, $clientUser) {
            return $e->requestId === $requestId
                && $e->companyId === $companyId
                && $e->userId === $clientUser->id;
        });
    }

    public function test_create_request_is_idempotent(): void
    {
        [$companyId, $gymId, $typeId] = $this->setupGymAndMembershipType();

        $clientUser = User::factory()->create();

        $first = $this->actingAs($clientUser, 'sanctum')
            ->withHeader('Idempotency-Key', 'req-idem-111')
            ->postJson('/api/v1/client/membership-requests', [
                'gym_id' => $gymId,
                'membership_type_id' => $typeId,
            ])
            ->assertStatus(201);

        $second = $this->actingAs($clientUser, 'sanctum')
            ->withHeader('Idempotency-Key', 'req-idem-111')
            ->postJson('/api/v1/client/membership-requests', [
                'gym_id' => $gymId,
                'membership_type_id' => $typeId,
            ])
            ->assertStatus(201);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('sales_membership_requests', 1);
    }

    public function test_cannot_create_request_for_inactive_membership_type(): void
    {
        [$companyId, $gymId, $typeId] = $this->setupGymAndMembershipType();

        /** @var SetMembershipTypeActive $toggleActive */
        $toggleActive = $this->app->make(SetMembershipTypeActive::class);
        $toggleActive->execute($companyId, $typeId, false);

        $clientUser = User::factory()->create();

        $this->actingAs($clientUser, 'sanctum')
            ->postJson('/api/v1/client/membership-requests', [
                'gym_id' => $gymId,
                'membership_type_id' => $typeId,
            ])
            ->assertStatus(422);
    }

    public function test_client_lists_own_membership_requests(): void
    {
        [$companyId, $gymId, $typeId] = $this->setupGymAndMembershipType();

        $client1 = User::factory()->create();
        $client2 = User::factory()->create();

        $this->actingAs($client1, 'sanctum')->postJson('/api/v1/client/membership-requests', [
            'gym_id' => $gymId,
            'membership_type_id' => $typeId,
        ])->assertStatus(201);

        $this->actingAs($client1, 'sanctum')->postJson('/api/v1/client/membership-requests', [
            'gym_id' => $gymId,
            'membership_type_id' => $typeId,
        ])->assertStatus(201);

        $this->actingAs($client2, 'sanctum')->postJson('/api/v1/client/membership-requests', [
            'gym_id' => $gymId,
            'membership_type_id' => $typeId,
        ])->assertStatus(201);

        $response = $this->actingAs($client1, 'sanctum')->getJson('/api/v1/client/membership-requests');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
