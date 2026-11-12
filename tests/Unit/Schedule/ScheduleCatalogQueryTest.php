<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Schedule\Contracts\ScheduleCatalogQueryInterface;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Shared\Application\CurrentCompanyInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ScheduleCatalogQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_upcoming_scheduled_sessions_for_gym_with_trainers_and_avoids_n_plus_one(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $companies = $this->app->make(CompanyRepositoryInterface::class);
        $gyms = $this->app->make(GymRepositoryInterface::class);
        $trainers = $this->app->make(TrainerRepositoryInterface::class);
        $templates = $this->app->make(ClassTemplateRepositoryInterface::class);
        $sessions = $this->app->make(ClassSessionRepositoryInterface::class);

        $companyA = $companies->create($ownerA->id, 'Corp A');
        $companyB = $companies->create($ownerB->id, 'Corp B');

        $gymA = $gyms->create($companyA, 'Gym A', null);
        $gymA2 = $gyms->create($companyA, 'Gym A2', null);
        $gymB = $gyms->create($companyB, 'Gym B', null);

        $trainer1 = $trainers->create($companyA, $gymA, 'Тимур');
        $trainer2 = $trainers->create($companyA, $gymA, 'Мадина');
        $trainerOther = $trainers->create($companyA, $gymA2, 'Рустам');

        $template1 = $templates->create($companyA, $gymA, $trainer1->id(), 'Йога', null, 1, 540, 60, 10);
        $template2 = $templates->create($companyA, $gymA, $trainer2->id(), 'Кроссфит', null, 1, 600, 60, 8);
        $templateOtherGym = $templates->create($companyA, $gymA2, $trainerOther->id(), 'Бокс', null, 1, 600, 60, 12);

        // 1) Прошедшая сессия -> исключается
        $sessions->createIfNotExistsFromTemplate(
            $template1,
            new DateTimeImmutable('2026-11-12 08:00:00+00:00'),
            new DateTimeImmutable('2026-11-12 09:00:00+00:00'),
        );

        // 2) Отменённая будущая сессия -> исключается
        $cancelled = $sessions->createIfNotExistsFromTemplate(
            $template1,
            new DateTimeImmutable('2026-11-13 09:00:00+00:00'),
            new DateTimeImmutable('2026-11-13 10:00:00+00:00'),
        );
        $this->assertNotNull($cancelled);
        $cancelled->cancel();
        $sessions->save($cancelled);

        // 3) Две подходящие будущие сессии; у первой заняты 2 места
        $upcomingSecond = $sessions->createIfNotExistsFromTemplate(
            $template2,
            new DateTimeImmutable('2026-11-14 10:00:00+00:00'),
            new DateTimeImmutable('2026-11-14 11:00:00+00:00'),
        );
        $this->assertNotNull($upcomingSecond);

        $upcomingFirst = $sessions->createIfNotExistsFromTemplate(
            $template1,
            new DateTimeImmutable('2026-11-13 12:00:00+00:00'),
            new DateTimeImmutable('2026-11-13 13:00:00+00:00'),
        );
        $this->assertNotNull($upcomingFirst);
        $upcomingFirst->reserveSeat();
        $upcomingFirst->reserveSeat();
        $sessions->save($upcomingFirst);

        // 4) За пределами периода to -> исключается
        $sessions->createIfNotExistsFromTemplate(
            $template1,
            new DateTimeImmutable('2026-11-25 09:00:00+00:00'),
            new DateTimeImmutable('2026-11-25 10:00:00+00:00'),
        );

        // 5) Другой зал -> исключается
        $sessions->createIfNotExistsFromTemplate(
            $templateOtherGym,
            new DateTimeImmutable('2026-11-13 14:00:00+00:00'),
            new DateTimeImmutable('2026-11-13 15:00:00+00:00'),
        );

        // Деактивированный после планирования тренер должен сохранять имя в уже созданном слоте
        $trainer2->deactivate();
        $trainers->save($trainer2);

        // Чужая текущая компания не должна скрывать публичное расписание
        $this->app->make(CurrentCompanyInterface::class)->setId($companyB);

        $query = $this->app->make(ScheduleCatalogQueryInterface::class);
        $now = new DateTimeImmutable('2026-11-12 10:00:00+00:00');
        $from = new DateTimeImmutable('2026-11-12 00:00:00+00:00');
        $to = new DateTimeImmutable('2026-11-19 23:59:59+00:00');

        DB::enableQueryLog();
        DB::flushQueryLog();
        $items = $query->listUpcomingForGym($gymA, $from, $to, $now);
        $queryCount = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(2, $queryCount);
        $this->assertCount(2, $items);

        $this->assertSame($upcomingFirst->publicId(), $items[0]->publicId);
        $this->assertSame('Йога', $items[0]->title);
        $this->assertSame(10, $items[0]->capacity);
        $this->assertSame(8, $items[0]->availableSeats);
        $this->assertSame($trainer1->publicId(), $items[0]->trainerPublicId);
        $this->assertSame('Тимур', $items[0]->trainerName);
        $this->assertSame('2026-11-13T12:00:00+00:00', $items[0]->startsAt->format(DATE_ATOM));
        $this->assertSame('2026-11-13T13:00:00+00:00', $items[0]->endsAt->format(DATE_ATOM));

        $this->assertSame($upcomingSecond->publicId(), $items[1]->publicId);
        $this->assertSame('Кроссфит', $items[1]->title);
        $this->assertSame(8, $items[1]->capacity);
        $this->assertSame(8, $items[1]->availableSeats);
        $this->assertSame($trainer2->publicId(), $items[1]->trainerPublicId);
        $this->assertSame('Мадина', $items[1]->trainerName);

        $this->assertSame([], $query->listUpcomingForGym($gymB, $from, $to, $now));

        // Полностью прошлый период не должен делать запросов к БД
        DB::flushQueryLog();
        $past = $query->listUpcomingForGym(
            $gymA,
            new DateTimeImmutable('2026-11-01 00:00:00+00:00'),
            new DateTimeImmutable('2026-11-12 09:59:59+00:00'),
            $now,
        );
        $this->assertSame([], $past);
        $this->assertCount(0, DB::getQueryLog());
    }
}

