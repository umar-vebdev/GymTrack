<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSessionStatus;
use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassSession;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ClassSessionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function createTemplate(string $suffix = 'A', int $capacity = 2): ClassTemplate
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$suffix}");
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, "Gym {$suffix}", null);
        $trainer = $this->app->make(TrainerRepositoryInterface::class)->create($companyId, $gymId, "Trainer {$suffix}");

        return $this->app->make(ClassTemplateRepositoryInterface::class)->create(
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
    }

    public function test_entity_guards_time_range_seats_and_cancellation(): void
    {
        $starts = new DateTimeImmutable('2026-11-09 09:00:00+00:00');
        $ends = new DateTimeImmutable('2026-11-09 10:00:00+00:00');

        $session = new ClassSession(
            id: 1,
            publicId: '01j00000000000000000000000',
            companyId: 10,
            gymId: 20,
            templateId: 30,
            trainerId: 40,
            title: 'Йога',
            capacity: 2,
            bookedCount: 0,
            startsAt: $starts,
            endsAt: $ends,
            status: ClassSessionStatus::SCHEDULED,
        );

        $this->assertSame(2, $session->availableSeats());
        $session->reserveSeat();
        $session->reserveSeat();
        $this->assertSame(0, $session->availableSeats());

        try {
            $session->reserveSeat();
            $this->fail('Exceeding capacity must throw.');
        } catch (InvalidClassSession) {
            $this->addToAssertionCount(1);
        }

        $session->releaseSeat();
        $this->assertSame(1, $session->availableSeats());

        $session->cancel();
        $this->assertSame(ClassSessionStatus::CANCELLED, $session->status());

        try {
            $session->reserveSeat();
            $this->fail('Booking cancelled session must throw.');
        } catch (InvalidClassSession) {
            $this->addToAssertionCount(1);
        }

        try {
            new ClassSession(
                1,
                '01j00000000000000000000000',
                10,
                20,
                30,
                40,
                'Йога',
                2,
                0,
                $ends,
                $starts,
                ClassSessionStatus::SCHEDULED,
            );
            $this->fail('startsAt >= endsAt must throw.');
        } catch (InvalidClassSession) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_repository_creates_idempotently_locks_for_update_and_isolates_companies(): void
    {
        $templateA = $this->createTemplate('A', 2);
        $templateB = $this->createTemplate('B', 2);
        $repo = $this->app->make(ClassSessionRepositoryInterface::class);

        $starts1 = new DateTimeImmutable('2026-11-09 09:00:00+00:00');
        $ends1 = new DateTimeImmutable('2026-11-09 10:00:00+00:00');
        $starts2 = new DateTimeImmutable('2026-11-16 09:00:00+00:00');
        $ends2 = new DateTimeImmutable('2026-11-16 10:00:00+00:00');

        $first = $repo->createIfNotExistsFromTemplate($templateA, $starts1, $ends1);
        $this->assertNotNull($first);
        $this->assertSame(26, strlen($first->publicId()));
        $this->assertSame(ClassSessionStatus::SCHEDULED, $first->status());

        $duplicate = $repo->createIfNotExistsFromTemplate($templateA, $starts1, $ends1);
        $this->assertNull($duplicate);

        $second = $repo->createIfNotExistsFromTemplate($templateA, $starts2, $ends2);
        $this->assertNotNull($second);

        $foreign = $repo->createIfNotExistsFromTemplate($templateB, $starts1, $ends1);
        $this->assertNotNull($foreign);

        DB::transaction(function () use ($repo, $templateA, $first): void {
            $locked = $repo->findByIdForUpdate($templateA->companyId(), $first->id());
            $this->assertNotNull($locked);
            $locked->reserveSeat();
            $locked->cancel();
            $repo->save($locked);
        });

        $reloaded = $repo->findById($templateA->companyId(), $first->id());
        $this->assertNotNull($reloaded);
        $this->assertSame(1, $reloaded->bookedCount());
        $this->assertSame(ClassSessionStatus::CANCELLED, $reloaded->status());

        $inFirstWeek = $repo->listForGymInPeriod(
            $templateA->companyId(),
            $templateA->gymId(),
            new DateTimeImmutable('2026-11-09 00:00:00+00:00'),
            new DateTimeImmutable('2026-11-15 23:59:59+00:00'),
        );
        $this->assertCount(1, $inFirstWeek);
        $this->assertSame($first->id(), $inFirstWeek[0]->id());

        $this->assertNull($repo->findById($templateA->companyId(), $foreign->id()));
        $this->assertNull($repo->findByIdForUpdate($templateA->companyId(), $foreign->id()));
    }
}
