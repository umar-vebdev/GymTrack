<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Memberships\Application\Commands\CreateMembershipTypeCommand;
use App\Modules\Memberships\Application\CreateMembershipType;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyMembershipRequestsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{int, int, int, User}
     */
    private function createCompanyWithStaff(): array
    {
        $owner = User::factory()->create();

        /** @var CompanyRepositoryInterface $companies */
        $companies = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companies->create($owner->id, 'Titan Gym');

        /** @var StaffMemberRepositoryInterface $staff */
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $staff->create($owner->id, $companyId, Role::OWNER);

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Titan Central', 'Ayni st 45');

        /** @var CreateMembershipType $createType */
        $createType = $this->app->make(CreateMembershipType::class);
        $typeId = $createType->execute(new CreateMembershipTypeCommand(
            companyId: $companyId,
            name: 'Monthly Unlimited',
            priceMinor: 40000,
            currency: 'TJS',
            kind: MembershipKind::Duration,
            startMode: StartMode::FirstVisit,
            visitsCount: null,
            durationDays: 30,
            validityDays: null,
            dailyLimit: 1,
            timeFromMinute: null,
            timeToMinute: null,
            freezeMaxDays: 0,
            freezeMaxTimes: 0
        ));

        return [$companyId, $gymId, $typeId, $owner];
    }

    public function test_guest_cannot_access_company_membership_requests(): void
    {
        $this->getJson('/api/v1/business/membership-requests')->assertStatus(401);
    }

    public function test_non_staff_user_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/business/membership-requests')
            ->assertStatus(403);
    }

    public function test_staff_lists_company_membership_requests(): void
    {
        [$companyId, $gymId, $typeId, $owner] = $this->createCompanyWithStaff();

        $clientUser = User::factory()->create([
            'name' => 'Alisher Navoi',
        ]);

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);
        $created = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/business/membership-requests');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $created->id())
            ->assertJsonPath('data.0.company_id', $companyId)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_staff_filters_requests_by_status(): void
    {
        [$companyId, $gymId, $typeId, $owner] = $this->createCompanyWithStaff();

        $client1 = User::factory()->create();
        $client2 = User::factory()->create();

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);

        $pendingReq = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $client1->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $cancelledReq = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $client2->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('+24 hours')
        );
        $cancelledReq->cancel();
        $requests->save($cancelledReq);

        // Filter pending
        $pendingResponse = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/business/membership-requests?status=pending');

        $pendingResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pendingReq->id())
            ->assertJsonPath('data.0.status', 'pending');

        // Filter cancelled
        $cancelledResponse = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/business/membership-requests?status=cancelled');

        $cancelledResponse->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $cancelledReq->id())
            ->assertJsonPath('data.0.status', 'cancelled');
    }

    public function test_multi_tenant_isolation_staff_cannot_see_other_company_requests(): void
    {
        [$company1Id, $gym1Id, $type1Id, $owner1] = $this->createCompanyWithStaff();
        [$company2Id, $gym2Id, $type2Id, $owner2] = $this->createCompanyWithStaff();

        $clientUser = User::factory()->create();

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);

        $req1 = $requests->create(
            companyId: $company1Id,
            gymId: $gym1Id,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $type1Id,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $req2 = $requests->create(
            companyId: $company2Id,
            gymId: $gym2Id,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $type2Id,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        // Owner 1 only sees req1
        $res1 = $this->actingAs($owner1, 'sanctum')->getJson('/api/v1/business/membership-requests');
        $res1->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $req1->id());

        // Owner 2 only sees req2
        $res2 = $this->actingAs($owner2, 'sanctum')->getJson('/api/v1/business/membership-requests');
        $res2->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $req2->id());
    }
}
