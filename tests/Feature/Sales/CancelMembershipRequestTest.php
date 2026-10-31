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
use App\Modules\Sales\Domain\Events\MembershipRequestCancelled;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class CancelMembershipRequestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{int, int, int, User}
     */
    private function setupCompany(): array
    {
        $owner = User::factory()->create();

        /** @var CompanyRepositoryInterface $companies */
        $companies = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companies->create($owner->id, 'Apex Fitness');

        /** @var StaffMemberRepositoryInterface $staff */
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $staff->create($owner->id, $companyId, Role::OWNER);

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Apex Branch', 'Rudaki ave 99');

        /** @var CreateMembershipType $createType */
        $createType = $this->app->make(CreateMembershipType::class);
        $typeId = $createType->execute(new CreateMembershipTypeCommand(
            companyId: $companyId,
            name: 'Single Entry',
            priceMinor: 5000,
            currency: 'TJS',
            kind: MembershipKind::Single,
            startMode: StartMode::OnSale,
            visitsCount: null,
            durationDays: null,
            validityDays: null,
            dailyLimit: 1,
            timeFromMinute: null,
            timeToMinute: null,
            freezeMaxDays: 0,
            freezeMaxTimes: 0
        ));

        return [$companyId, $gymId, $typeId, $owner];
    }

    public function test_guest_cannot_cancel_request(): void
    {
        $this->postJson('/api/v1/business/membership-requests/1/cancel')->assertStatus(401);
    }

    public function test_non_staff_cannot_cancel_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/business/membership-requests/1/cancel')
            ->assertStatus(403);
    }

    public function test_staff_cancels_pending_request_with_event(): void
    {
        Event::fake([MembershipRequestCancelled::class]);

        [$companyId, $gymId, $typeId, $owner] = $this->setupCompany();
        $clientUser = User::factory()->create();

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);
        $request = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/membership-requests/{$request->id()}/cancel", [
                'reason' => 'Client changed mind',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $request->id(),
                    'status' => 'cancelled',
                ],
            ]);

        $this->assertDatabaseHas('sales_membership_requests', [
            'id' => $request->id(),
            'status' => 'cancelled',
        ]);

        Event::assertDispatched(MembershipRequestCancelled::class, function (MembershipRequestCancelled $e) use ($request, $owner) {
            return $e->requestId === $request->id()
                && $e->cancelledByUserId === $owner->id
                && $e->reason === 'Client changed mind';
        });
    }

    public function test_cannot_cancel_already_cancelled_or_paid_request(): void
    {
        [$companyId, $gymId, $typeId, $owner] = $this->setupCompany();
        $clientUser = User::factory()->create();

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);
        $request = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $request->cancel();
        $requests->save($request);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/membership-requests/{$request->id()}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Невозможно отменить заявку в статусе cancelled.');
    }
}
