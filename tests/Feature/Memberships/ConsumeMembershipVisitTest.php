<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Contracts\MembershipVisitConsumerInterface;
use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ConsumeMembershipVisitTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $clientId;

    private int $typeId;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->companyId = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Co'])->id;

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $this->clientId = $clients->create($this->companyId, 'John', '+992900111222');

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $this->typeId = $types->create($this->companyId, 'Visits', new Money(1000, 'TJS'), MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 10));
    }

    private function add(MembershipStatus $status, ?int $visits, ?string $endsAt = null): int
    {
        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);

        return $repo->create($this->companyId, $this->clientId, $this->typeId, $status, $visits, null, $endsAt === null ? null : new DateTimeImmutable($endsAt));
    }

    private function consumer(): MembershipVisitConsumerInterface
    {
        return $this->app->make(MembershipVisitConsumerInterface::class);
    }

    private function membership(int $id): Membership
    {
        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);

        return $repo->findById($id) ?? throw new LogicException("Membership #{$id} not found.");
    }

    public function test_candidate_is_usable_membership_ending_earlier(): void
    {
        $this->add(MembershipStatus::Active, 5, '2027-12-31 00:00:00');
        $sooner = $this->add(MembershipStatus::Active, 5, '2027-01-31 00:00:00');

        $candidate = $this->consumer()->findCandidate($this->companyId, $this->clientId, new DateTimeImmutable('2026-10-30'));

        $this->assertNotNull($candidate);
        $this->assertSame($sooner, $candidate->membershipId);
        $this->assertFalse($candidate->isFrozen);
        $this->assertSame(1, $candidate->dailyLimit);
        $this->assertNull($candidate->timeFromMinute);
        $this->assertNull($candidate->timeToMinute);
    }

    public function test_frozen_membership_is_returned_only_when_nothing_usable(): void
    {
        $frozen = $this->add(MembershipStatus::Frozen, 5);

        $candidate = $this->consumer()->findCandidate($this->companyId, $this->clientId, new DateTimeImmutable('2026-10-30'));
        $this->assertNotNull($candidate);
        $this->assertSame($frozen, $candidate->membershipId);
        $this->assertTrue($candidate->isFrozen);

        $active = $this->add(MembershipStatus::Active, 3);
        $candidate = $this->consumer()->findCandidate($this->companyId, $this->clientId, new DateTimeImmutable('2026-10-30'));
        $this->assertNotNull($candidate);
        $this->assertSame($active, $candidate->membershipId);
    }

    public function test_no_candidate_when_nothing_suitable(): void
    {
        $this->add(MembershipStatus::Expired, 0);
        $this->add(MembershipStatus::Cancelled, 5);

        $this->assertNull($this->consumer()->findCandidate($this->companyId, $this->clientId, new DateTimeImmutable('2026-10-30')));
    }

    public function test_consume_and_restore_visit_roundtrip_reactivates_expired(): void
    {
        $id = $this->add(MembershipStatus::Active, 1);
        $now = new DateTimeImmutable('2026-10-30');

        $this->consumer()->consumeVisit($id, $now);
        $this->assertSame(0, $this->membership($id)->remainingVisits());
        $this->assertSame(MembershipStatus::Expired, $this->membership($id)->status());

        $this->consumer()->restoreVisit($id, $now);
        $this->assertSame(1, $this->membership($id)->remainingVisits());
        $this->assertSame(MembershipStatus::Active, $this->membership($id)->status());
    }
}
