<?php

declare(strict_types=1);

namespace Tests\Unit\Visits;

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
use App\Modules\Visits\Contracts\VisitsAnalyticsQueryInterface;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VisitsAnalyticsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_completed_visits_by_method_and_day_and_finds_active_clients(): void
    {
        $owner = User::factory()->create();
        $companyId = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Gym A'])->id;

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Hall', 'Dushanbe');

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $client1 = $clients->create($companyId, 'Active Client', '+992900111001');
        $client2 = $clients->create($companyId, 'Old Client', '+992900111002');

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyId,
            'Visits',
            new Money(10000, 'TJS'),
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 10),
        );

        /** @var MembershipRepositoryInterface $memberships */
        $memberships = $this->app->make(MembershipRepositoryInterface::class);
        $m1 = $memberships->create($companyId, $client1, $typeId, MembershipStatus::Active, 10, null, null);
        $m2 = $memberships->create($companyId, $client2, $typeId, MembershipStatus::Active, 10, null, null);

        /** @var VisitRepositoryInterface $visits */
        $visits = $this->app->make(VisitRepositoryInterface::class);

        $visits->create($companyId, $gymId, $client1, $m1, VisitMethod::Qr, new DateTimeImmutable('2026-10-25 10:00:00'), null);
        $visits->create($companyId, $gymId, $client1, $m1, VisitMethod::Manual, new DateTimeImmutable('2026-10-26 11:00:00'), $owner->id);
        $visits->create($companyId, $gymId, $client2, $m2, VisitMethod::Qr, new DateTimeImmutable('2026-10-05 09:00:00'), null);

        // Отменённый визит не должен учитываться
        $cancelled = $visits->create($companyId, $gymId, $client2, $m2, VisitMethod::Manual, new DateTimeImmutable('2026-10-26 12:00:00'), $owner->id);
        $cancelled->cancel('Mistake', $owner->id, new DateTimeImmutable('2026-10-26 12:05:00'));
        $visits->save($cancelled);

        /** @var VisitsAnalyticsQueryInterface $analytics */
        $analytics = $this->app->make(VisitsAnalyticsQueryInterface::class);

        $summary = $analytics->summaryForPeriod(
            $companyId,
            new DateTimeImmutable('2026-10-01 00:00:00'),
            new DateTimeImmutable('2026-10-31 23:59:59'),
        );

        $this->assertSame(3, $summary->totalVisits);
        $this->assertSame(2, $summary->qrVisits);
        $this->assertSame(1, $summary->manualVisits);
        $this->assertSame([
            '2026-10-05' => 1,
            '2026-10-25' => 1,
            '2026-10-26' => 1,
        ], $summary->byDay);

        // Активные клиенты за последние 14 дней (с 17 по 31 октября) — только $client1
        $activeIds = $analytics->activeClientIdsBetween(
            $companyId,
            new DateTimeImmutable('2026-10-17 00:00:00'),
            new DateTimeImmutable('2026-10-31 23:59:59'),
        );

        $this->assertSame([$client1], $activeIds);
    }
}

