<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\ClassBookingRepositoryInterface;
use App\Modules\Schedule\Domain\ClassBookingStatus;
use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ClassBookingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{companyId: int, gymId: int, session: ClassSession, clientId: int}
     */
    private function createSetup(string $suffix = 'A'): array
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$suffix}");
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, "Gym {$suffix}", null);
        $trainer = $this->app->make(TrainerRepositoryInterface::class)->create($companyId, $gymId, "Trainer {$suffix}");

        $template = $this->app->make(ClassTemplateRepositoryInterface::class)->create(
            companyId: $companyId,
            gymId: $gymId,
            trainerId: $trainer->id(),
            title: "Занятие {$suffix}",
            description: null,
            weekday: 1,
            startTimeMinute: 540,
            durationMinutes: 60,
            capacity: 10,
        );

        $session = $this->app->make(ClassSessionRepositoryInterface::class)->createIfNotExistsFromTemplate(
            $template,
            new DateTimeImmutable('2026-11-16 09:00:00+00:00'),
            new DateTimeImmutable('2026-11-16 10:00:00+00:00'),
        );
        $this->assertNotNull($session);

        $clientId = $this->app->make(ClientRepositoryInterface::class)->create(
            $companyId,
            "Client {$suffix}",
            '+99290000'.random_int(1000, 9999),
        );

        return [
            'companyId' => $companyId,
            'gymId' => $gymId,
            'session' => $session,
            'clientId' => $clientId,
        ];
    }

    public function test_it_creates_cancels_rebooks_and_lists_bookings_for_session_and_client(): void
    {
        $a = $this->createSetup('A');
        $b = $this->createSetup('B');
        $repo = $this->app->make(ClassBookingRepositoryInterface::class);
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $secondClientA = $clients->create($a['companyId'], 'Second A', '+992900111222');

        $t1 = new DateTimeImmutable('2026-11-12 08:00:00+00:00');
        $t2 = new DateTimeImmutable('2026-11-12 08:10:00+00:00');
        $t3 = new DateTimeImmutable('2026-11-12 08:20:00+00:00');
        $t4 = new DateTimeImmutable('2026-11-12 08:30:00+00:00');

        $first = $repo->create(
            companyId: $a['companyId'],
            gymId: $a['gymId'],
            sessionId: $a['session']->id(),
            clientId: $a['clientId'],
            bookedAt: $t1,
            idempotencyKey: '  idem-1  ',
        );

        $this->assertSame(26, strlen($first->publicId()));
        $this->assertSame('idem-1', $first->idempotencyKey());
        $this->assertTrue($first->isBooked());

        $foundById = $repo->findById($first->id());
        $this->assertNotNull($foundById);
        $this->assertSame($first->id(), $foundById->id());

        $foundByKey = $repo->findByIdempotencyKey($a['companyId'], $a['clientId'], 'idem-1');
        $this->assertNotNull($foundByKey);
        $this->assertSame($first->id(), $foundByKey->id());
        $this->assertNull($repo->findByIdempotencyKey($a['companyId'], $a['clientId'], '   '));

        $active = $repo->findActiveForSessionAndClient($a['companyId'], $a['session']->id(), $a['clientId']);
        $this->assertNotNull($active);
        $this->assertSame($first->id(), $active->id());

        // Отмена через блокировку строки и сохранение
        DB::transaction(function () use ($repo, $a, $first, $t2): void {
            $locked = $repo->findByIdForUpdate($a['companyId'], $first->id());
            $this->assertNotNull($locked);
            $locked->cancel($t2);
            $repo->save($locked);
        });

        $this->assertNull($repo->findActiveForSessionAndClient($a['companyId'], $a['session']->id(), $a['clientId']));

        // Создаём абонемент и проверяем сохранение membership_id
        $typeId = $this->app->make(MembershipTypeRepositoryInterface::class)->create(
            $a['companyId'],
            '10 занятий',
            new Money(50000, 'TJS'),
            MembershipRules::create(
                kind: MembershipKind::Visits,
                startMode: StartMode::OnSale,
                visitsCount: 10,
                validityDays: 30,
            ),
        );
        $membershipId = $this->app->make(MembershipIssuerInterface::class)->issue(
            $a['companyId'],
            $a['clientId'],
            $typeId,
            $t1,
        );

        // Повторная запись после отмены допустима (partial unique index только для status = 'booked')
        $rebooked = $repo->create(
            companyId: $a['companyId'],
            gymId: $a['gymId'],
            sessionId: $a['session']->id(),
            clientId: $a['clientId'],
            bookedAt: $t3,
            idempotencyKey: null,
            membershipId: $membershipId,
        );
        $this->assertSame($membershipId, $rebooked->membershipId());

        $otherInSession = $repo->create(
            companyId: $a['companyId'],
            gymId: $a['gymId'],
            sessionId: $a['session']->id(),
            clientId: $secondClientA,
            bookedAt: $t2,
            idempotencyKey: null,
        );

        $foreignBooking = $repo->create(
            companyId: $b['companyId'],
            gymId: $b['gymId'],
            sessionId: $b['session']->id(),
            clientId: $b['clientId'],
            bookedAt: $t4,
            idempotencyKey: 'idem-1',
        );

        $sessionBookings = $repo->listForSession($a['companyId'], $a['session']->id());
        $this->assertSame(
            [$first->id(), $otherInSession->id(), $rebooked->id()],
            array_map(static fn (ClassBooking $booking): int => $booking->id(), $sessionBookings),
        );
        $this->assertSame([], $repo->listForSession($a['companyId'], $b['session']->id()));

        // Блокирующая выборка активных броней занятия возвращает только status = booked
        DB::transaction(function () use ($repo, $a, $b, $rebooked, $otherInSession, $membershipId): void {
            $activeLocked = $repo->listActiveForSessionForUpdate($a['companyId'], $a['session']->id());
            $this->assertSame(
                [$rebooked->id(), $otherInSession->id()],
                array_map(static fn (ClassBooking $booking): int => $booking->id(), $activeLocked),
            );
            $this->assertSame($membershipId, $activeLocked[0]->membershipId());
            $this->assertSame([], $repo->listActiveForSessionForUpdate($b['companyId'], $a['session']->id()));
        });

        // Список записей клиента с фильтрами и пагинацией
        $this->assertSame(['items' => [], 'total' => 0], $repo->listForClient([]));

        $allForUser = $repo->listForClient([$a['clientId'], $b['clientId']], page: 1, perPage: 2);
        $this->assertSame(3, $allForUser['total']);
        $this->assertSame(
            [$foreignBooking->id(), $rebooked->id()],
            array_map(static fn (ClassBooking $booking): int => $booking->id(), $allForUser['items']),
        );

        $onlyBookedInA = $repo->listForClient(
            clientIds: [$a['clientId'], $b['clientId']],
            companyId: $a['companyId'],
            status: ClassBookingStatus::BOOKED,
        );
        $this->assertSame(1, $onlyBookedInA['total']);
        $this->assertSame($rebooked->id(), $onlyBookedInA['items'][0]->id());
    }

    public function test_it_enforces_company_isolation_and_unique_constraints(): void
    {
        $a = $this->createSetup('A');
        $b = $this->createSetup('B');
        $repo = $this->app->make(ClassBookingRepositoryInterface::class);

        $bookedAt = new DateTimeImmutable('2026-11-12 09:00:00+00:00');
        $booking = $repo->create(
            companyId: $a['companyId'],
            gymId: $a['gymId'],
            sessionId: $a['session']->id(),
            clientId: $a['clientId'],
            bookedAt: $bookedAt,
            idempotencyKey: 'unique-key',
        );

        $this->assertNull($repo->findByIdForUpdate($b['companyId'], $booking->id()));
        $this->assertNull($repo->findByIdempotencyKey($b['companyId'], $a['clientId'], 'unique-key'));

        // Попытка сохранить поддельную сущность с чужим company_id не должна менять запись
        $forged = new ClassBooking(
            id: $booking->id(),
            publicId: $booking->publicId(),
            companyId: $b['companyId'],
            gymId: $a['gymId'],
            sessionId: $a['session']->id(),
            clientId: $a['clientId'],
            status: ClassBookingStatus::CANCELLED,
            idempotencyKey: 'unique-key',
            bookedAt: $bookedAt,
            cancelledAt: $bookedAt->modify('+5 minutes'),
        );
        $repo->save($forged);

        $untouched = $repo->findById($booking->id());
        $this->assertNotNull($untouched);
        $this->assertTrue($untouched->isBooked());

        // Повторная активная запись того же клиента на то же занятие запрещена на уровне БД
        try {
            DB::transaction(static function () use ($repo, $a, $bookedAt): void {
                $repo->create(
                    companyId: $a['companyId'],
                    gymId: $a['gymId'],
                    sessionId: $a['session']->id(),
                    clientId: $a['clientId'],
                    bookedAt: $bookedAt->modify('+1 minute'),
                    idempotencyKey: 'another-key',
                );
            });
            $this->fail('Duplicate active booking for session and client must violate partial unique index.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        // Повторный idempotency_key для того же клиента в компании запрещён даже на другое занятие
        $secondTemplate = $this->app->make(ClassTemplateRepositoryInterface::class)->create(
            companyId: $a['companyId'],
            gymId: $a['gymId'],
            trainerId: $a['session']->trainerId(),
            title: 'Стретчинг',
            description: null,
            weekday: 2,
            startTimeMinute: 600,
            durationMinutes: 60,
            capacity: 10,
        );
        $secondSession = $this->app->make(ClassSessionRepositoryInterface::class)->createIfNotExistsFromTemplate(
            $secondTemplate,
            new DateTimeImmutable('2026-11-17 10:00:00+00:00'),
            new DateTimeImmutable('2026-11-17 11:00:00+00:00'),
        );
        $this->assertNotNull($secondSession);

        try {
            DB::transaction(static function () use ($repo, $a, $secondSession, $bookedAt): void {
                $repo->create(
                    companyId: $a['companyId'],
                    gymId: $a['gymId'],
                    sessionId: $secondSession->id(),
                    clientId: $a['clientId'],
                    bookedAt: $bookedAt->modify('+2 minutes'),
                    idempotencyKey: 'unique-key',
                );
            });
            $this->fail('Duplicate (company_id, client_id, idempotency_key) must violate unique index.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
