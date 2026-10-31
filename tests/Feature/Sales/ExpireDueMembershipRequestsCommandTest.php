<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Memberships\Application\Commands\CreateMembershipTypeCommand;
use App\Modules\Memberships\Application\CreateMembershipType;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class ExpireDueMembershipRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_due_membership_requests(): void
    {
        $owner = User::factory()->create();

        /** @var CompanyRepositoryInterface $companies */
        $companies = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companies->create($owner->id, 'Power Gym');

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Power 1', 'Main ave');

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

        $client1 = User::factory()->create();
        $client2 = User::factory()->create();
        $client3 = User::factory()->create();

        /** @var MembershipRequestRepositoryInterface $requests */
        $requests = $this->app->make(MembershipRequestRepositoryInterface::class);

        // Request 1: expired 2 hours ago
        $expiredReq1 = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $client1->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('-2 hours')
        );

        // Request 2: expired 10 minutes ago
        $expiredReq2 = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $client2->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('-10 minutes')
        );

        // Request 3: active, expires in 20 hours
        $activeReq = $requests->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $client3->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: new DateTimeImmutable('+20 hours')
        );

        // Run artisan command
        $this->assertSame(0, Artisan::call('sales:expire-requests'));
        $this->assertStringContainsString('Отменено просроченных заявок: 2', Artisan::output());

        $this->assertDatabaseHas('sales_membership_requests', [
            'id' => $expiredReq1->id(),
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('sales_membership_requests', [
            'id' => $expiredReq2->id(),
            'status' => 'expired',
        ]);

        $this->assertDatabaseHas('sales_membership_requests', [
            'id' => $activeReq->id(),
            'status' => 'pending',
        ]);
    }
}
