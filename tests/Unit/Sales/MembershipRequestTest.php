<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Memberships\Application\Commands\CreateMembershipTypeCommand;
use App\Modules\Memberships\Application\CreateMembershipType;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Sales\Domain\MembershipRequest;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use App\Modules\Sales\Domain\MembershipRequestStatus;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MembershipRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_entity_invariants_and_transitions(): void
    {
        $now = new DateTimeImmutable('2026-10-31 10:00:00');
        $expiresAt = new DateTimeImmutable('2026-11-01 10:00:00');

        $request = new MembershipRequest(
            id: 1,
            companyId: 10,
            gymId: 20,
            userId: 30,
            clientId: 40,
            membershipTypeId: 50,
            status: MembershipRequestStatus::PENDING,
            expiresAt: $expiresAt
        );

        $this->assertSame(MembershipRequestStatus::PENDING, $request->status());
        $this->assertTrue($request->canBePaid($now));
        $this->assertFalse($request->isExpired($now));

        // After expiresAt: cannot be paid, isExpired = true
        $afterExpiration = new DateTimeImmutable('2026-11-01 10:00:01');
        $this->assertFalse($request->canBePaid($afterExpiration));
        $this->assertTrue($request->isExpired($afterExpiration));

        // Mark paid
        $paidAt = new DateTimeImmutable('2026-10-31 11:00:00');
        $request->markPaid(saleId: 100, membershipId: 200, paidAt: $paidAt);

        $this->assertSame(MembershipRequestStatus::PAID, $request->status());
        $this->assertSame(100, $request->saleId());
        $this->assertSame(200, $request->membershipId());
        $this->assertSame($paidAt, $request->paidAt());
        $this->assertFalse($request->canBePaid($now));

        // Cannot mark paid again
        $this->expectException(DomainException::class);
        $request->markPaid(101, 201, $paidAt);
    }

    public function test_cannot_cancel_already_paid_request(): void
    {
        $request = new MembershipRequest(
            id: 1,
            companyId: 10,
            gymId: 20,
            userId: 30,
            clientId: 40,
            membershipTypeId: 50,
            status: MembershipRequestStatus::PAID,
            expiresAt: new DateTimeImmutable('+24 hours')
        );

        $this->expectException(DomainException::class);
        $request->cancel();
    }

    public function test_repository_crud_and_queries(): void
    {
        $owner = User::factory()->create();
        $clientUser = User::factory()->create();

        /** @var CompanyRepositoryInterface $companies */
        $companies = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companies->create($owner->id, 'Fitness Corp');

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Main Gym', 'Dushanbe');

        /** @var CreateMembershipType $createType */
        $createType = $this->app->make(CreateMembershipType::class);
        $typeId = $createType->execute(new CreateMembershipTypeCommand(
            companyId: $companyId,
            name: 'Standard 12',
            priceMinor: 50000,
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

        /** @var MembershipRequestRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRequestRepositoryInterface::class);

        $expiresAt = new DateTimeImmutable('+24 hours');
        $created = $repo->create(
            companyId: $companyId,
            gymId: $gymId,
            userId: $clientUser->id,
            clientId: null,
            membershipTypeId: $typeId,
            expiresAt: $expiresAt,
            idempotencyKey: 'idem-req-123'
        );

        $this->assertGreaterThan(0, $created->id());
        $this->assertSame(MembershipRequestStatus::PENDING, $created->status());
        $this->assertSame($clientUser->id, $created->userId());

        // Find by ID and idempotency key
        $found = $repo->findById($created->id());
        $this->assertNotNull($found);
        $this->assertSame($created->id(), $found->id());

        $foundByKey = $repo->findByIdempotencyKey('idem-req-123');
        $this->assertNotNull($foundByKey);
        $this->assertSame($created->id(), $foundByKey->id());

        // List queries
        $byUser = $repo->listByUser($clientUser->id);
        $this->assertCount(1, $byUser);

        $byCompany = $repo->listByCompany($companyId, MembershipRequestStatus::PENDING);
        $this->assertCount(1, $byCompany);

        // Update / Save
        $found->expire();
        $repo->save($found);

        $updated = $repo->findById($created->id());
        $this->assertNotNull($updated);
        $this->assertSame(MembershipRequestStatus::EXPIRED, $updated->status());

        // Empty expired pending query since it's already EXPIRED
        $expiredPending = $repo->findPendingExpired(new DateTimeImmutable('+48 hours'));
        $this->assertCount(0, $expiredPending);
    }
}
