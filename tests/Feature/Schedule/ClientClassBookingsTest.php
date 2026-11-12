<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Schedule\Application\CancelClassSession;
use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClientClassBookingsTest extends TestCase
{
    use RefreshDatabase;

    private ?DateTimeImmutable $fixedNow = null;

    private bool $clockBound = false;

    private function bindFixedClock(string $time): void
    {
        $this->fixedNow = new DateTimeImmutable($time);

        if (!$this->clockBound) {
            $this->clockBound = true;
            $test = $this;

            $this->app->instance(Clock::class, new class($test) implements Clock
            {
                public function __construct(private readonly ClientClassBookingsTest $test) {}

                public function now(): DateTimeImmutable
                {
                    return $this->test->currentTestNow();
                }
            });
        }
    }

    public function currentTestNow(): DateTimeImmutable
    {
        return $this->fixedNow ?? new DateTimeImmutable;
    }

    /**
     * @return array{owner: User, companyId: int, gymId: int, template: ClassTemplate, session: ClassSession}
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
            'owner' => $owner,
            'companyId' => $companyId,
            'gymId' => $gymId,
            'template' => $template,
            'session' => $session,
        ];
    }

    public function test_client_can_book_idempotently_list_with_filters_and_cancel_booking(): void
    {
        $this->bindFixedClock('2026-11-16 08:00:00+00:00');

        $a = $this->createSessionSetup('A', 2, '2026-11-16 09:00:00+00:00', '2026-11-16 10:00:00+00:00');
        $b = $this->createSessionSetup('B', 2, '2026-11-16 11:00:00+00:00', '2026-11-16 12:00:00+00:00');

        $secondSessionA = $this->app->make(ClassSessionRepositoryInterface::class)->createIfNotExistsFromTemplate(
            $a['template'],
            new DateTimeImmutable('2026-11-23 09:00:00+00:00'),
            new DateTimeImmutable('2026-11-23 10:00:00+00:00'),
        );
        $this->assertNotNull($secondSessionA);

        $client = User::factory()->create();

        // Без авторизации -> 401
        $this->postJson("/api/v1/client/schedule/sessions/{$a['session']->id()}/book")
            ->assertStatus(401);
        $this->postJson('/api/v1/client/schedule/bookings/1/cancel')
            ->assertStatus(401);
        $this->getJson('/api/v1/client/schedule/bookings')
            ->assertStatus(401);

        // Запись с Idempotency-Key -> 201
        $bookA = $this->actingAs($client, 'sanctum')
            ->postJson(
                "/api/v1/client/schedule/sessions/{$a['session']->id()}/book",
                [],
                ['Idempotency-Key' => 'book-key-1'],
            );

        $bookA->assertCreated()
            ->assertJsonPath('data.company_id', $a['companyId'])
            ->assertJsonPath('data.gym_id', $a['gymId'])
            ->assertJsonPath('data.session_id', $a['session']->id())
            ->assertJsonPath('data.status', 'booked')
            ->assertJsonPath('data.cancelled_at', null);

        $bookingAId = (int) $bookA->json('data.id');
        $this->assertGreaterThan(0, $bookingAId);

        // Повтор с тем же Idempotency-Key -> возвращает ту же запись и не занимает второе место
        $this->actingAs($client, 'sanctum')
            ->postJson(
                "/api/v1/client/schedule/sessions/{$a['session']->id()}/book",
                [],
                ['Idempotency-Key' => 'book-key-1'],
            )
            ->assertCreated()
            ->assertJsonPath('data.id', $bookingAId);

        $sessionAfterBook = $this->app->make(ClassSessionRepositoryInterface::class)
            ->findById($a['companyId'], $a['session']->id());
        $this->assertNotNull($sessionAfterBook);
        $this->assertSame(1, $sessionAfterBook->bookedCount());

        // Повторное использование Idempotency-Key на другом занятии той же компании -> 422
        $this->actingAs($client, 'sanctum')
            ->postJson(
                "/api/v1/client/schedule/sessions/{$secondSessionA->id()}/book",
                [],
                ['Idempotency-Key' => 'book-key-1'],
            )
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CLASS_BOOKING_REJECTED');

        // Повторная запись на то же занятие с другим ключом -> 422
        $this->actingAs($client, 'sanctum')
            ->postJson(
                "/api/v1/client/schedule/sessions/{$a['session']->id()}/book",
                [],
                ['Idempotency-Key' => 'book-key-2'],
            )
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CLASS_BOOKING_REJECTED');

        // Запись в другую компанию (независимая CRM-карточка клиента)
        $this->bindFixedClock('2026-11-16 08:15:00+00:00');
        $bookB = $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/client/schedule/sessions/{$b['session']->id()}/book")
            ->assertCreated();
        $bookingBId = (int) $bookB->json('data.id');

        // Отмена записи A до начала занятия -> 200 и освобождение места
        $this->bindFixedClock('2026-11-16 08:30:00+00:00');
        $cancelA = $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/client/schedule/bookings/{$bookingAId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.id', $bookingAId)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertNotNull($cancelA->json('data.cancelled_at'));

        // Повторная отмена идемпотентна
        $this->actingAs($client, 'sanctum')
            ->postJson("/api/v1/client/schedule/bookings/{$bookingAId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.id', $bookingAId)
            ->assertJsonPath('data.status', 'cancelled');

        $sessionAfterCancel = $this->app->make(ClassSessionRepositoryInterface::class)
            ->findById($a['companyId'], $a['session']->id());
        $this->assertNotNull($sessionAfterCancel);
        $this->assertSame(0, $sessionAfterCancel->bookedCount());

        // Список своих записей: без фильтров, по статусу, по компании и с пагинацией
        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/schedule/bookings')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 20);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/schedule/bookings?status=booked')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bookingBId)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/schedule/bookings?company_id='.$a['companyId'].'&status=cancelled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $bookingAId)
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/schedule/bookings?page=2&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2);

        // Невалидные параметры запроса -> 422 VALIDATION_FAILED
        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v1/client/schedule/bookings?status=invalid&per_page=101')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['status', 'per_page']]]);
    }

    public function test_client_booking_and_cancellation_errors_and_isolation(): void
    {
        $this->bindFixedClock('2026-11-16 08:00:00+00:00');

        $setup = $this->createSessionSetup('A', 1, '2026-11-16 09:00:00+00:00', '2026-11-16 10:00:00+00:00');
        $client1 = User::factory()->create();
        $client2 = User::factory()->create();

        // Несуществующее занятие -> 404
        $this->actingAs($client1, 'sanctum')
            ->postJson('/api/v1/client/schedule/sessions/999999/book')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        // Слишком длинный Idempotency-Key -> 422
        $this->actingAs($client1, 'sanctum')
            ->postJson(
                "/api/v1/client/schedule/sessions/{$setup['session']->id()}/book",
                [],
                ['Idempotency-Key' => str_repeat('a', 129)],
            )
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CLASS_BOOKING_REJECTED');

        // Первый клиент занимает единственное место
        $book = $this->actingAs($client1, 'sanctum')
            ->postJson("/api/v1/client/schedule/sessions/{$setup['session']->id()}/book")
            ->assertCreated();
        $bookingId = (int) $book->json('data.id');

        // Второй клиент не может записаться (нет мест) -> 422
        $this->actingAs($client2, 'sanctum')
            ->postJson("/api/v1/client/schedule/sessions/{$setup['session']->id()}/book")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CLASS_SESSION_UNAVAILABLE');

        // Чужой клиент не видит и не может отменить чужую бронь -> 404
        $this->actingAs($client2, 'sanctum')
            ->postJson("/api/v1/client/schedule/bookings/{$bookingId}/cancel")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        // Несуществующая бронь -> 404
        $this->actingAs($client1, 'sanctum')
            ->postJson('/api/v1/client/schedule/bookings/999999/cancel')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        // Запись на отменённое занятие -> 422
        $cancelledSessionSetup = $this->createSessionSetup('C', 3, '2026-11-17 09:00:00+00:00', '2026-11-17 10:00:00+00:00');
        $this->app->make(CancelClassSession::class)->execute(
            $cancelledSessionSetup['companyId'],
            $cancelledSessionSetup['session']->id(),
        );
        $this->actingAs($client2, 'sanctum')
            ->postJson("/api/v1/client/schedule/sessions/{$cancelledSessionSetup['session']->id()}/book")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CLASS_SESSION_UNAVAILABLE');

        // Когда занятие уже началось -> ни записаться на новое, ни отменить активное нельзя -> 422
        $this->bindFixedClock('2026-11-16 09:00:00+00:00');

        $this->actingAs($client1, 'sanctum')
            ->postJson("/api/v1/client/schedule/bookings/{$bookingId}/cancel")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CLASS_BOOKING_CANCELLATION_NOT_ALLOWED');

        $startedSetup = $this->createSessionSetup('D', 3, '2026-11-16 09:00:00+00:00', '2026-11-16 10:00:00+00:00');
        $this->actingAs($client2, 'sanctum')
            ->postJson("/api/v1/client/schedule/sessions/{$startedSetup['session']->id()}/book")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CLASS_SESSION_UNAVAILABLE');
    }

    public function test_owner_admin_and_trainer_can_view_session_bookings_with_company_isolation(): void
    {
        $this->bindFixedClock('2026-11-16 08:00:00+00:00');

        $a = $this->createSessionSetup('A', 5, '2026-11-16 09:00:00+00:00', '2026-11-16 10:00:00+00:00');
        $b = $this->createSessionSetup('B', 5, '2026-11-16 09:00:00+00:00', '2026-11-16 10:00:00+00:00');

        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $adminA = User::factory()->create();
        $trainerA = User::factory()->create();
        $staff->create($a['owner']->id, $a['companyId'], Role::OWNER);
        $staff->create($adminA->id, $a['companyId'], Role::ADMIN);
        $staff->create($trainerA->id, $a['companyId'], Role::TRAINER);

        $adminB = User::factory()->create();
        $trainerB = User::factory()->create();
        $staff->create($b['owner']->id, $b['companyId'], Role::OWNER);
        $staff->create($adminB->id, $b['companyId'], Role::ADMIN);
        $staff->create($trainerB->id, $b['companyId'], Role::TRAINER);

        $client1 = User::factory()->create();
        $client2 = User::factory()->create();

        // Без авторизации -> 401
        $this->getJson("/api/v1/business/schedule/sessions/{$a['session']->id()}/bookings")
            ->assertStatus(401);

        // Обычный клиент не является сотрудником/тренером -> 403
        $this->actingAs($client1, 'sanctum')
            ->getJson("/api/v1/business/schedule/sessions/{$a['session']->id()}/bookings")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Две записи в компанию A (одна остаётся активной, вторая отменяется) и одна в B
        $firstBook = $this->actingAs($client1, 'sanctum')
            ->postJson("/api/v1/client/schedule/sessions/{$a['session']->id()}/book")
            ->assertCreated();
        $firstBookingId = (int) $firstBook->json('data.id');
        $firstClientId = (int) $firstBook->json('data.client_id');

        $this->bindFixedClock('2026-11-16 08:10:00+00:00');
        $secondBook = $this->actingAs($client2, 'sanctum')
            ->postJson("/api/v1/client/schedule/sessions/{$a['session']->id()}/book")
            ->assertCreated();
        $secondBookingId = (int) $secondBook->json('data.id');
        $secondClientId = (int) $secondBook->json('data.client_id');

        $this->bindFixedClock('2026-11-16 08:20:00+00:00');
        $this->actingAs($client2, 'sanctum')
            ->postJson("/api/v1/client/schedule/bookings/{$secondBookingId}/cancel")
            ->assertOk();

        $this->actingAs($client1, 'sanctum')
            ->postJson("/api/v1/client/schedule/sessions/{$b['session']->id()}/book")
            ->assertCreated();

        // Owner, admin и trainer своей компании видят обе записи занятия A в хронологическом порядке
        foreach ([$a['owner'], $adminA, $trainerA] as $actorA) {
            $res = $this->actingAs($actorA, 'sanctum')
                ->getJson("/api/v1/business/schedule/sessions/{$a['session']->id()}/bookings")
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.id', $firstBookingId)
                ->assertJsonPath('data.0.client_id', $firstClientId)
                ->assertJsonPath('data.0.status', 'booked')
                ->assertJsonPath('data.0.cancelled_at', null)
                ->assertJsonPath('data.1.id', $secondBookingId)
                ->assertJsonPath('data.1.client_id', $secondClientId)
                ->assertJsonPath('data.1.status', 'cancelled');

            $this->assertNotNull($res->json('data.1.cancelled_at'));
        }

        // Представители чужой компании не видят занятие компании A -> 404
        foreach ([$b['owner'], $adminB, $trainerB] as $actorB) {
            $this->actingAs($actorB, 'sanctum')
                ->getJson("/api/v1/business/schedule/sessions/{$a['session']->id()}/bookings")
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'NOT_FOUND');
        }

        // Несуществующее занятие -> 404
        $this->actingAs($adminA, 'sanctum')
            ->getJson('/api/v1/business/schedule/sessions/999999/bookings')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
