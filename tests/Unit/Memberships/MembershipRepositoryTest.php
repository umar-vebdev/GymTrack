<?php

declare(strict_types=1);

namespace Tests\Unit\Memberships;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MembershipRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{int, int, int}
     */
    private function prepareData(): array
    {
        $user = User::factory()->create();
        $company = CompanyModel::create(['owner_id' => $user->id, 'name' => 'Company']);

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($company->id, 'John', '+992900111222');

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $company->id,
            '10 занятий',
            new Money(50000, 'TJS'),
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 10, validityDays: 30)
        );

        return [$company->id, $clientId, $typeId];
    }

    public function test_it_creates_and_finds_membership(): void
    {
        [$companyId, $clientId, $typeId] = $this->prepareData();
        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);

        $endsAt = new DateTimeImmutable('2026-11-01 10:00:00');
        $id = $repo->create($companyId, $clientId, $typeId, MembershipStatus::Active, 10, new DateTimeImmutable('2026-10-02 10:00:00'), $endsAt);

        $membership = $repo->findById($id);

        $this->assertNotNull($membership);
        $this->assertSame($clientId, $membership->clientId());
        $this->assertSame(MembershipStatus::Active, $membership->status());
        $this->assertSame(10, $membership->remainingVisits());
        $this->assertSame(MembershipKind::Visits, $membership->rules()->kind);
        $this->assertSame('2026-11-01 10:00:00', $membership->endsAt()?->format('Y-m-d H:i:s'));
    }

    public function test_it_saves_changed_state(): void
    {
        [$companyId, $clientId, $typeId] = $this->prepareData();
        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);
        $id = $repo->create($companyId, $clientId, $typeId, MembershipStatus::Active, 10, null, new DateTimeImmutable('2026-12-01 10:00:00'));

        $membership = $repo->findById($id);
        $this->assertNotNull($membership);
        $membership->consumeVisit(new DateTimeImmutable('2026-10-05 10:00:00'));
        $repo->save($membership);

        $reloaded = $repo->findById($id);
        $this->assertNotNull($reloaded);
        $this->assertSame(9, $reloaded->remainingVisits());

        $reloaded->cancel('Ошибка выдачи');
        $repo->save($reloaded);

        $cancelled = $repo->findById($id);
        $this->assertNotNull($cancelled);
        $this->assertSame(MembershipStatus::Cancelled, $cancelled->status());
        $this->assertSame('Ошибка выдачи', $cancelled->cancellationReason());
    }

    public function test_it_loads_with_lock_inside_transaction(): void
    {
        [$companyId, $clientId, $typeId] = $this->prepareData();
        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);
        $id = $repo->create($companyId, $clientId, $typeId, MembershipStatus::Active, 3, null, null);

        $found = DB::transaction(fn () => $repo->findByIdForUpdate($id));

        $this->assertNotNull($found);
        $this->assertSame(3, $found->remainingVisits());
        $this->assertNull($repo->findByIdForUpdate($id + 999));
    }

    public function test_database_rejects_negative_remaining_visits(): void
    {
        [$companyId, $clientId, $typeId] = $this->prepareData();
        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);
        $id = $repo->create($companyId, $clientId, $typeId, MembershipStatus::Active, 1, null, null);

        $this->expectException(QueryException::class);

        DB::table('memberships')->where('id', $id)->update(['remaining_visits' => -1]);
    }
}
