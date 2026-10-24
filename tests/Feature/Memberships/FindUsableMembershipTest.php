<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Contracts\MembershipLookupInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FindUsableMembershipTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private int $clientId;

    private int $visitsTypeId;

    private int $durationTypeId;

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
        $this->visitsTypeId = $types->create($this->companyId, 'Visits', new Money(1000, 'TJS'), MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 10));
        $this->durationTypeId = $types->create($this->companyId, 'Month', new Money(1000, 'TJS'), MembershipRules::create(MembershipKind::Duration, StartMode::OnSale, durationDays: 30));
    }

    private function add(int $typeId, MembershipStatus $status, ?int $visits, ?string $endsAt, ?int $clientId = null): int
    {
        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);

        return $repo->create(
            $this->companyId,
            $clientId ?? $this->clientId,
            $typeId,
            $status,
            $visits,
            null,
            $endsAt === null ? null : new DateTimeImmutable($endsAt)
        );
    }

    private function find(?int $clientId = null, ?int $companyId = null): ?int
    {
        /** @var MembershipLookupInterface $lookup */
        $lookup = $this->app->make(MembershipLookupInterface::class);

        return $lookup->findUsableForClient(
            $companyId ?? $this->companyId,
            $clientId ?? $this->clientId,
            new DateTimeImmutable('2026-10-10 12:00:00')
        )?->id;
    }

    public function test_it_picks_the_membership_that_ends_earlier(): void
    {
        $later = $this->add($this->durationTypeId, MembershipStatus::Active, null, '2026-12-01 00:00:00');
        $earlier = $this->add($this->visitsTypeId, MembershipStatus::Active, 5, '2026-11-01 00:00:00');

        $this->assertSame($earlier, $this->find());
        $this->assertNotSame($later, $this->find());
    }

    public function test_membership_without_end_goes_last(): void
    {
        $unlimited = $this->add($this->visitsTypeId, MembershipStatus::Active, 5, null);
        $limited = $this->add($this->durationTypeId, MembershipStatus::Active, null, '2026-12-01 00:00:00');

        $this->assertSame($limited, $this->find());
        $this->assertNotSame($unlimited, $this->find());
    }

    public function test_it_skips_expired_empty_frozen_and_cancelled(): void
    {
        $this->add($this->durationTypeId, MembershipStatus::Active, null, '2026-10-01 00:00:00');
        $this->add($this->visitsTypeId, MembershipStatus::Active, 0, '2026-12-01 00:00:00');
        $this->add($this->durationTypeId, MembershipStatus::Frozen, null, '2026-12-01 00:00:00');
        $this->add($this->durationTypeId, MembershipStatus::Cancelled, null, '2026-12-01 00:00:00');
        $this->add($this->durationTypeId, MembershipStatus::Expired, null, '2026-12-01 00:00:00');

        $this->assertNull($this->find());
    }

    public function test_pending_membership_is_usable(): void
    {
        $pending = $this->add($this->durationTypeId, MembershipStatus::Pending, null, null);

        $this->assertSame($pending, $this->find());
    }

    public function test_it_does_not_return_memberships_of_other_client_or_company(): void
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $otherClient = $clients->create($this->companyId, 'Other', '+992900333444');
        $this->add($this->durationTypeId, MembershipStatus::Active, null, '2026-12-01 00:00:00', $otherClient);

        $this->assertNull($this->find());

        $foreignCompany = CompanyModel::create(['owner_id' => User::factory()->create()->id, 'name' => 'Other Co'])->id;
        $this->assertNull($this->find($otherClient, $foreignCompany));
    }
}
