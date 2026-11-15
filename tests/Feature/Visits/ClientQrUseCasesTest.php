<?php

declare(strict_types=1);

namespace Tests\Feature\Visits;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Visits\Application\CheckInByClientQr;
use App\Modules\Visits\Application\Commands\CheckInByClientQrCommand;
use App\Modules\Visits\Application\GenerateClientQr;
use App\Modules\Visits\Application\GenerateGymQr;
use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\Exceptions\GymNotFound;
use App\Modules\Visits\Domain\Exceptions\InvalidQrToken;
use App\Modules\Visits\Domain\QrTokenServiceInterface;
use App\Modules\Visits\Domain\VisitMethod;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientQrUseCasesTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;

    private int $companyId;

    private int $gymId;

    private int $otherCompanyId;

    private int $otherGymId;

    private User $clientUser;

    private int $clientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staffUser = User::factory()->create();
        $this->companyId = CompanyModel::create(['owner_id' => $this->staffUser->id, 'name' => 'Alpha Gym'])->id;

        /** @var GymRepositoryInterface $gyms */
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $this->gymId = $gyms->create(
            companyId: $this->companyId,
            name: 'Main Gym',
            address: 'ул. Рудаки, 10',
            geoCheckEnabled: true,
            geoCheckRadiusMeters: 100,
        );

        // Координаты витрины включены, чтобы убедиться, что личный QR не требует геолокацию клиента
        $profile = GymProfile::draft($this->gymId);
        $profile->update('Описание', 'ул. Рудаки, 10', 38.5598, 68.7870, '+992900000111', [], []);
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        $otherOwner = User::factory()->create();
        $this->otherCompanyId = CompanyModel::create(['owner_id' => $otherOwner->id, 'name' => 'Beta Gym'])->id;
        $this->otherGymId = $gyms->create($this->otherCompanyId, 'Foreign Gym', 'ул. Сомони, 5');

        $this->clientUser = User::factory()->create(['phone' => '+992900888777', 'is_phone_verified' => true]);

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $this->clientId = $clients->create($this->companyId, 'Самир', '+992900888777', $this->clientUser->id);
    }

    private function issueMembership(int $visitsCount = 10, int $dailyLimit = 1): int
    {
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $this->companyId,
            '10 занятий',
            new Money(25000, 'TJS'),
            MembershipRules::create(
                kind: MembershipKind::Visits,
                startMode: StartMode::OnSale,
                visitsCount: $visitsCount,
                dailyLimit: $dailyLimit,
            ),
        );

        /** @var MembershipRepositoryInterface $repo */
        $repo = $this->app->make(MembershipRepositoryInterface::class);

        return $repo->create($this->companyId, $this->clientId, $typeId, MembershipStatus::Active, $visitsCount, null, null);
    }

    public function test_client_qr_token_generation_and_verification_window(): void
    {
        $clock = new class implements Clock
        {
            public DateTimeImmutable $now;

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };
        $clock->now = new DateTimeImmutable('2026-11-15 10:00:10 UTC');
        $this->app->instance(Clock::class, $clock);

        /** @var GenerateClientQr $generator */
        $generator = $this->app->make(GenerateClientQr::class);
        $generated = $generator->execute($this->clientUser->id);

        $this->assertSame(QrTokenServiceInterface::INTERVAL_SECONDS, $generated['ttlSeconds']);
        $token = $generated['payload']->toTokenString();

        /** @var QrTokenServiceInterface $service */
        $service = $this->app->make(QrTokenServiceInterface::class);

        // Текущий слот
        $this->assertSame($this->clientUser->id, $service->verifyClientToken($token, new DateTimeImmutable('2026-11-15 10:00:50 UTC')));
        // Предыдущий слот (+1 минута)
        $this->assertSame($this->clientUser->id, $service->verifyClientToken($token, new DateTimeImmutable('2026-11-15 10:01:30 UTC')));

        // Старше 2 минут -> InvalidQrToken
        try {
            $service->verifyClientToken($token, new DateTimeImmutable('2026-11-15 10:02:05 UTC'));
            $this->fail('Ожидался отказ для просроченного токена.');
        } catch (InvalidQrToken $e) {
            $this->assertSame('Код недействителен, попросите администратора', $e->getMessage());
        }

        // Подмена userId в токене -> InvalidQrToken
        $parts = explode('|', $token);
        $forged = "client|{$this->staffUser->id}|{$parts[2]}|{$parts[3]}";
        try {
            $service->verifyClientToken($forged, $clock->now);
            $this->fail('Ожидался отказ для подделанного userId.');
        } catch (InvalidQrToken $e) {
            $this->assertSame('Код недействителен, попросите администратора', $e->getMessage());
        }

        // Токен зала не принимается как личный QR клиента
        /** @var GenerateGymQr $gymQr */
        $gymQr = $this->app->make(GenerateGymQr::class);
        $gymToken = $gymQr->execute($this->companyId, $this->gymId)['payload']->toTokenString();
        $this->expectException(InvalidQrToken::class);
        $service->verifyClientToken($gymToken, $clock->now);
    }

    public function test_staff_checks_in_client_by_personal_qr_with_idempotency_and_isolation(): void
    {
        $membershipId = $this->issueMembership(10);

        /** @var GenerateClientQr $generator */
        $generator = $this->app->make(GenerateClientQr::class);
        $token = $generator->execute($this->clientUser->id)['payload']->toTokenString();

        /** @var CheckInByClientQr $checkIn */
        $checkIn = $this->app->make(CheckInByClientQr::class);

        $first = $checkIn->execute(new CheckInByClientQrCommand(
            companyId: $this->companyId,
            gymId: $this->gymId,
            staffUserId: $this->staffUser->id,
            qrToken: $token,
            idempotencyKey: 'client-qr-key-1',
            device: 'Reception Scanner',
        ));

        $this->assertSame(9, $first->remainingVisits);
        $this->assertSame($membershipId, $first->visit->membershipId());
        $this->assertSame(VisitMethod::ClientQr, $first->visit->method());
        $this->assertSame($this->staffUser->id, $first->visit->registeredByUserId());

        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $this->companyId,
            'action' => 'VISIT_REGISTERED',
            'entity_type' => 'Visit',
            'entity_id' => (string) $first->visit->id(),
        ]);

        // Идемпотентный повтор не списывает второй раз
        $duplicate = $checkIn->execute(new CheckInByClientQrCommand(
            companyId: $this->companyId,
            gymId: $this->gymId,
            staffUserId: $this->staffUser->id,
            qrToken: $token,
            idempotencyKey: 'client-qr-key-1',
        ));

        $this->assertSame($first->visit->id(), $duplicate->visit->id());
        $this->assertSame(9, $duplicate->remainingVisits);

        // Повтор без ключа в тот же день -> DAILY_LIMIT_EXCEEDED
        try {
            $checkIn->execute(new CheckInByClientQrCommand(
                companyId: $this->companyId,
                gymId: $this->gymId,
                staffUserId: $this->staffUser->id,
                qrToken: $token,
            ));
            $this->fail('Ожидался отказ по дневному лимиту.');
        } catch (CheckInDenied $e) {
            $this->assertSame(CheckInDenied::REASON_DAILY_LIMIT_EXCEEDED, $e->reason);
        }

        // Чужой зал -> GymNotFound
        $this->expectException(GymNotFound::class);
        $checkIn->execute(new CheckInByClientQrCommand(
            companyId: $this->companyId,
            gymId: $this->otherGymId,
            staffUserId: $this->staffUser->id,
            qrToken: $token,
        ));
    }
}
