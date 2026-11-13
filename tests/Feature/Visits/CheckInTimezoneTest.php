<?php

declare(strict_types=1);

namespace Tests\Feature\Visits;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Visits\Application\CheckInByQr;
use App\Modules\Visits\Application\Commands\CheckInByQrCommand;
use App\Modules\Visits\Application\GenerateGymQr;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CheckInTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_calculates_visit_date_in_gym_timezone_and_stores_visited_at_in_utc(): void
    {
        $owner = User::factory()->create();
        $companyId = CompanyModel::create(['owner_id' => $owner->id, 'name' => 'Dushanbe Co'])->id;

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gyms->create($companyId, 'Main Gym', 'Rudaki 1', 'Asia/Dushanbe');

        $clientUser = User::factory()->create(['phone' => '+992900777888', 'is_phone_verified' => true]);

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'Ali', '+992900777888', $clientUser->id);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyId,
            'Unlimited',
            new Money(20000, 'TJS'),
            MembershipRules::create(
                kind: MembershipKind::Unlimited,
                startMode: StartMode::OnSale,
                durationDays: 30,
                dailyLimit: 1,
            ),
        );

        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);
        $issuer->issue(
            $companyId,
            $clientId,
            $typeId,
            new DateTimeImmutable('2026-10-15 00:00:00', new DateTimeZone('UTC')),
            $owner->id,
        );

        // 18:30 UTC = 23:30 Asia/Dushanbe (2026-10-30)
        // 19:30 UTC = 00:30 Asia/Dushanbe (уже 2026-10-31!)
        $mutableClock = new class implements Clock
        {
            public DateTimeImmutable $current;

            public function now(): DateTimeImmutable
            {
                return $this->current;
            }
        };
        $mutableClock->current = new DateTimeImmutable('2026-10-30 18:30:00', new DateTimeZone('UTC'));
        $this->app->instance(Clock::class, $mutableClock);

        /** @var GenerateGymQr $qrGen */
        $qrGen = $this->app->make(GenerateGymQr::class);
        /** @var CheckInByQr $checkIn */
        $checkIn = $this->app->make(CheckInByQr::class);

        $firstToken = $qrGen->execute($companyId, $gymId)['payload']->toTokenString();
        $firstResult = $checkIn->execute(new CheckInByQrCommand(
            userId: $clientUser->id,
            qrToken: $firstToken,
            device: 'iOS',
            idempotencyKey: 'tz-1',
        ));

        $firstRow = DB::table('visits_visits')->where('id', $firstResult->visit->id())->first();
        $this->assertNotNull($firstRow);
        $this->assertSame('2026-10-30', $firstRow->visit_date);
        $this->assertSame('2026-10-30 18:30:00', $firstRow->visited_at);

        // Через час (19:30 UTC) в Душанбе уже 2026-10-31 00:30, поэтому новый день разрешает отметку
        $mutableClock->current = new DateTimeImmutable('2026-10-30 19:30:00', new DateTimeZone('UTC'));
        $secondToken = $qrGen->execute($companyId, $gymId)['payload']->toTokenString();
        $secondResult = $checkIn->execute(new CheckInByQrCommand(
            userId: $clientUser->id,
            qrToken: $secondToken,
            device: 'iOS',
            idempotencyKey: 'tz-2',
        ));

        $secondRow = DB::table('visits_visits')->where('id', $secondResult->visit->id())->first();
        $this->assertNotNull($secondRow);
        $this->assertSame('2026-10-31', $secondRow->visit_date);
        $this->assertSame('2026-10-30 19:30:00', $secondRow->visited_at);
    }
}
