<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Models\User;
use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Clients\Infrastructure\Persistence\ClientModel;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Schedule\Application\BookClassSession;
use App\Modules\Schedule\Application\BookClassSessionCommand;
use App\Modules\Schedule\Application\CancelClassBooking;
use App\Modules\Schedule\Application\CancelClassSession;
use App\Modules\Schedule\Application\ListClientBookings;
use App\Modules\Schedule\Application\ListSessionBookings;
use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\ClassBookingStatus;
use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassBooking;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassSession;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClassBookingUseCasesTest extends TestCase
{
    use RefreshDatabase;

    private function bindFixedClock(string $time): void
    {
        $fixed = new DateTimeImmutable($time);

        $this->app->instance(Clock::class, new class($fixed) implements Clock
        {
            public function __construct(private readonly DateTimeImmutable $fixed) {}

            public function now(): DateTimeImmutable
            {
                return $this->fixed;
            }
        });
    }

    private function assertSessionBookedCount(int $companyId, int $sessionId, int $expected): void
    {
        $session = $this->app->make(ClassSessionRepositoryInterface::class)->findById($companyId, $sessionId);
        $this->assertNotNull($session);
        $this->assertSame($expected, $session->bookedCount());
    }

    /**
     * @return array{companyId: int, gymId: int, session: ClassSession, trainerId: int}
     */
    private function createSessionSetup(
        string $suffix = 'A',
        int $capacity = 2,
        string $startsAt = '2026-11-16 09:00:00+00:00',
        string $endsAt = '2026-11-16 10:00:00+00:00',
    ): array {
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
            capacity: $capacity,
        );

        $session = $this->app->make(ClassSessionRepositoryInterface::class)->createIfNotExistsFromTemplate(
            $template,
            new DateTimeImmutable($startsAt),
            new DateTimeImmutable($endsAt),
        );
        $this->assertNotNull($session);

        return [
            'companyId' => $companyId,
            'gymId' => $gymId,
            'session' => $session,
            'trainerId' => $trainer->id(),
        ];
    }

    public function test_book_creates_client_card_when_missing_reserves_seat_and_is_idempotent(): void
    {
        $this->bindFixedClock('2026-11-12 08:00:00+00:00');
        $setup = $this->createSessionSetup(capacity: 1);
        $clientUser = User::factory()->create(['name' => 'Алишер', 'phone' => '+992900123456']);

        /** @var BookClassSession $book */
        $book = $this->app->make(BookClassSession::class);
        $clients = $this->app->make(ClientLookupInterface::class);
        $sessions = $this->app->make(ClassSessionRepositoryInterface::class);

        $this->assertSame(0, ClientModel::withoutGlobalScopes()->where('company_id', $setup['companyId'])->count());

        $first = $book->execute(new BookClassSessionCommand(
            userId: $clientUser->id,
            sessionId: $setup['session']->id(),
            idempotencyKey: '  booking-key-1  ',
        ));

        $createdClientId = $clients->findIdByUser($clientUser->id, $setup['companyId']);
        $this->assertNotNull($createdClientId);
        $this->assertSame($createdClientId, $first->clientId());
        $this->assertSame($setup['companyId'], $first->companyId());
        $this->assertSame($setup['gymId'], $first->gymId());
        $this->assertSame($setup['session']->id(), $first->sessionId());
        $this->assertSame(ClassBookingStatus::BOOKED, $first->status());
        $this->assertSame('booking-key-1', $first->idempotencyKey());
        $this->assertSame('2026-11-12 08:00:00', $first->bookedAt()->format('Y-m-d H:i:s'));

        $updatedSession = $sessions->findById($setup['companyId'], $setup['session']->id());
        $this->assertNotNull($updatedSession);
        $this->assertSame(1, $updatedSession->bookedCount());
        $this->assertSame(0, $updatedSession->availableSeats());

        // Повторный вызов с тем же Idempotency-Key возвращает ту же запись, даже если мест больше нет или занятие отменено
        $this->app->make(CancelClassSession::class)->execute($setup['companyId'], $setup['session']->id());

        $retried = $book->execute(new BookClassSessionCommand(
            userId: $clientUser->id,
            sessionId: $setup['session']->id(),
            idempotencyKey: 'booking-key-1',
        ));

        $this->assertSame($first->id(), $retried->id());
        $this->assertSame($first->publicId(), $retried->publicId());

        // Тот же ключ идемпотентности нельзя переиспользовать для другого занятия компании
        $secondTemplate = $this->app->make(ClassTemplateRepositoryInterface::class)->create(
            companyId: $setup['companyId'],
            gymId: $setup['gymId'],
            trainerId: $setup['trainerId'],
            title: 'Бокс',
            description: null,
            weekday: 2,
            startTimeMinute: 600,
            durationMinutes: 60,
            capacity: 5,
        );
        $secondSession = $sessions->createIfNotExistsFromTemplate(
            $secondTemplate,
            new DateTimeImmutable('2026-11-17 10:00:00+00:00'),
            new DateTimeImmutable('2026-11-17 11:00:00+00:00'),
        );
        $this->assertNotNull($secondSession);

        try {
            $book->execute(new BookClassSessionCommand(
                userId: $clientUser->id,
                sessionId: $secondSession->id(),
                idempotencyKey: 'booking-key-1',
            ));
            $this->fail('Reusing idempotency key on another session must throw InvalidClassBooking.');
        } catch (InvalidClassBooking) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_book_rejects_missing_cancelled_started_full_and_duplicate_without_polluting_crm(): void
    {
        $this->bindFixedClock('2026-11-12 09:00:00+00:00');
        $setup = $this->createSessionSetup(capacity: 1);
        $sessions = $this->app->make(ClassSessionRepositoryInterface::class);
        $book = $this->app->make(BookClassSession::class);

        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $thirdUser = User::factory()->create();

        // 1. Несуществующее занятие -> ScheduleResourceNotFound
        try {
            $book->execute(new BookClassSessionCommand($firstUser->id, 999999));
            $this->fail('Missing session must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        // 2. Запись первого клиента занимает единственное место; повторная запись без того же ключа -> InvalidClassBooking
        $book->execute(new BookClassSessionCommand($firstUser->id, $setup['session']->id(), 'key-a'));

        try {
            $book->execute(new BookClassSessionCommand($firstUser->id, $setup['session']->id(), 'key-b'));
            $this->fail('Duplicate active booking must throw InvalidClassBooking.');
        } catch (InvalidClassBooking) {
            $this->addToAssertionCount(1);
        }

        // 3. Нет свободных мест -> InvalidClassSession, и транзакция откатывает создание карточки CRM для secondUser
        try {
            $book->execute(new BookClassSessionCommand($secondUser->id, $setup['session']->id()));
            $this->fail('Full session must throw InvalidClassSession.');
        } catch (InvalidClassSession) {
            $this->assertSame(1, ClientModel::withoutGlobalScopes()->where('company_id', $setup['companyId'])->count());
        }

        // 4. Отменённое занятие -> InvalidClassSession
        $cancelledSetup = $this->createSessionSetup('B', capacity: 5);
        $this->app->make(CancelClassSession::class)->execute($cancelledSetup['companyId'], $cancelledSetup['session']->id());

        try {
            $book->execute(new BookClassSessionCommand($thirdUser->id, $cancelledSetup['session']->id()));
            $this->fail('Cancelled session must throw InvalidClassSession.');
        } catch (InvalidClassSession) {
            $this->addToAssertionCount(1);
        }

        // 5. Уже начавшееся занятие -> InvalidClassSession
        $pastSetup = $this->createSessionSetup(
            suffix: 'C',
            capacity: 5,
            startsAt: '2026-11-12 09:00:00+00:00',
            endsAt: '2026-11-12 10:00:00+00:00',
        );

        try {
            $book->execute(new BookClassSessionCommand($thirdUser->id, $pastSetup['session']->id()));
            $this->fail('Already started session must throw InvalidClassSession.');
        } catch (InvalidClassSession) {
            $this->addToAssertionCount(1);
        }

        // 6. Слишком длинный ключ идемпотентности -> InvalidClassBooking
        try {
            $book->execute(new BookClassSessionCommand($thirdUser->id, $setup['session']->id(), str_repeat('x', 129)));
            $this->fail('Overlong idempotency key must throw InvalidClassBooking.');
        } catch (InvalidClassBooking) {
            $this->addToAssertionCount(1);
        }

        $reloaded = $sessions->findById($setup['companyId'], $setup['session']->id());
        $this->assertNotNull($reloaded);
        $this->assertSame(1, $reloaded->bookedCount());
    }

    public function test_cancel_is_idempotent_releases_seat_allows_rebook_and_enforces_ownership_and_start_time(): void
    {
        $this->bindFixedClock('2026-11-12 08:00:00+00:00');
        $setup = $this->createSessionSetup(capacity: 2);
        $book = $this->app->make(BookClassSession::class);
        $cancel = $this->app->make(CancelClassBooking::class);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $booking1 = $book->execute(new BookClassSessionCommand($user1->id, $setup['session']->id(), 'u1-first'));
        $book->execute(new BookClassSessionCommand($user2->id, $setup['session']->id(), 'u2-first'));

        $this->assertSessionBookedCount($setup['companyId'], $setup['session']->id(), 2);

        // Чужой пользователь и несуществующая запись -> ScheduleResourceNotFound
        try {
            $cancel->execute($user2->id, $booking1->id());
            $this->fail('Cancelling foreign booking must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        try {
            $cancel->execute($user1->id, 999999);
            $this->fail('Cancelling missing booking must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        // Отмена освобождает 1 место (2 -> 1)
        $this->bindFixedClock('2026-11-12 09:00:00+00:00');
        $cancelled = $this->app->make(CancelClassBooking::class)->execute($user1->id, $booking1->id());
        $this->assertSame(ClassBookingStatus::CANCELLED, $cancelled->status());
        $this->assertSame('2026-11-12 09:00:00', $cancelled->cancelledAt()?->format('Y-m-d H:i:s'));
        $this->assertSessionBookedCount($setup['companyId'], $setup['session']->id(), 1);

        // Повторная отмена идемпотентна: не меняет время отмены и не освобождает место повторно
        $this->bindFixedClock('2026-11-12 10:00:00+00:00');
        $cancelledAgain = $this->app->make(CancelClassBooking::class)->execute($user1->id, $booking1->id());
        $this->assertSame('2026-11-12 09:00:00', $cancelledAgain->cancelledAt()?->format('Y-m-d H:i:s'));
        $this->assertSessionBookedCount($setup['companyId'], $setup['session']->id(), 1);

        // После отмены клиент может записаться на то же занятие повторно (с новым ключом)
        $rebooked = $this->app->make(BookClassSession::class)->execute(new BookClassSessionCommand($user1->id, $setup['session']->id(), 'u1-second'));
        $this->assertNotSame($booking1->id(), $rebooked->id());
        $this->assertSessionBookedCount($setup['companyId'], $setup['session']->id(), 2);

        // Нельзя отменить запись на уже начавшееся занятие со статусом scheduled
        $this->bindFixedClock('2026-11-16 09:00:00+00:00');
        try {
            $this->app->make(CancelClassBooking::class)->execute($user1->id, $rebooked->id());
            $this->fail('Cancelling started scheduled session must throw InvalidClassBooking.');
        } catch (InvalidClassBooking) {
            $this->addToAssertionCount(1);
        }

        // Но если само занятие отменено залом, клиент может отменить свою запись
        $this->app->make(CancelClassSession::class)->execute($setup['companyId'], $setup['session']->id());
        $cancelledAfterSessionCancel = $this->app->make(CancelClassBooking::class)->execute($user1->id, $rebooked->id());
        $this->assertSame(ClassBookingStatus::CANCELLED, $cancelledAfterSessionCancel->status());
    }

    public function test_list_session_bookings_and_list_client_bookings_respect_isolation_and_filters(): void
    {
        $this->bindFixedClock('2026-11-12 08:00:00+00:00');
        $a = $this->createSessionSetup('A', capacity: 5);
        $b = $this->createSessionSetup('B', capacity: 5);

        $listSession = $this->app->make(ListSessionBookings::class);
        $listClient = $this->app->make(ListClientBookings::class);

        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $emptyUser = User::factory()->create();

        $b1 = $this->app->make(BookClassSession::class)->execute(new BookClassSessionCommand($user->id, $a['session']->id()));
        $this->bindFixedClock('2026-11-12 08:10:00+00:00');
        $b2 = $this->app->make(BookClassSession::class)->execute(new BookClassSessionCommand($otherUser->id, $a['session']->id()));
        $this->bindFixedClock('2026-11-12 08:20:00+00:00');
        $b3 = $this->app->make(BookClassSession::class)->execute(new BookClassSessionCommand($user->id, $b['session']->id()));
        $this->app->make(CancelClassBooking::class)->execute($user->id, $b1->id());

        $sessionBookings = $listSession->execute($a['companyId'], $a['session']->id());
        $this->assertSame(
            [$b1->id(), $b2->id()],
            array_map(static fn (ClassBooking $booking): int => $booking->id(), $sessionBookings),
        );

        try {
            $listSession->execute($b['companyId'], $a['session']->id());
            $this->fail('Listing foreign session bookings must throw ScheduleResourceNotFound.');
        } catch (ScheduleResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        $emptyList = $listClient->execute($emptyUser->id);
        $this->assertSame(['items' => [], 'total' => 0], $emptyList);

        $allUserBookings = $listClient->execute($user->id);
        $this->assertSame(2, $allUserBookings['total']);
        $this->assertSame(
            [$b3->id(), $b1->id()],
            array_map(static fn (ClassBooking $booking): int => $booking->id(), $allUserBookings['items']),
        );

        $filteredByCompanyAndStatus = $listClient->execute(
            userId: $user->id,
            companyId: $a['companyId'],
            status: ClassBookingStatus::CANCELLED,
        );
        $this->assertSame(1, $filteredByCompanyAndStatus['total']);
        $this->assertSame($b1->id(), $filteredByCompanyAndStatus['items'][0]->id());
    }
}
