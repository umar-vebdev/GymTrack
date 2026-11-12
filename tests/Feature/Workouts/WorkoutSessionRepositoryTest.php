<?php

declare(strict_types=1);

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSessionStatus;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkoutSessionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private WorkoutSessionRepositoryInterface $sessions;

    private ExerciseRepositoryInterface $exercises;

    private WorkoutPlanRepositoryInterface $plans;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sessions = $this->app->make(WorkoutSessionRepositoryInterface::class);
        $this->exercises = $this->app->make(ExerciseRepositoryInterface::class);
        $this->plans = $this->app->make(WorkoutPlanRepositoryInterface::class);
    }

    public function test_it_starts_logs_sets_completes_and_queries_exercise_progress(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $squat = $this->exercises->create(null, 'Squat', MuscleGroup::LEGS);
        $bench = $this->exercises->create(null, 'Bench Press', MuscleGroup::CHEST);
        $plan = $this->plans->create($user->id, null, 'Leg Plan', null, PlanLevel::Beginner, false, []);

        $day1Start = new DateTimeImmutable('2026-11-10T09:00:00+00:00');
        $day1 = $this->sessions->start($user->id, $plan->id(), 'Day 1', $day1Start);

        $day1 = $this->sessions->addSet($user->id, $day1->id(), $squat->id(), 80000, 8, new DateTimeImmutable('2026-11-10T09:05:00+00:00'));
        $this->assertNotNull($day1);
        $day1 = $this->sessions->addSet($user->id, $day1->id(), $squat->id(), 85000, 6, new DateTimeImmutable('2026-11-10T09:10:00+00:00'));
        $this->assertNotNull($day1);
        $day1->complete(new DateTimeImmutable('2026-11-10T09:40:00+00:00'), 'Good progress');
        $savedDay1 = $this->sessions->save($day1);

        $this->assertSame(WorkoutSessionStatus::Completed, $savedDay1->status());
        $this->assertSame([1, 2], array_map(static fn ($s): int => $s->setNumber(), $savedDay1->sets()));

        // Вторая сессия: жим и присед; третья отменённая не должна попадать в прогресс
        $day2 = $this->sessions->start($user->id, null, 'Day 2', new DateTimeImmutable('2026-11-12T09:00:00+00:00'));
        $this->sessions->addSet($user->id, $day2->id(), $bench->id(), 60000, 10, new DateTimeImmutable('2026-11-12T09:05:00+00:00'));
        $this->sessions->addSet($user->id, $day2->id(), $squat->id(), 90000, 5, new DateTimeImmutable('2026-11-12T09:15:00+00:00'));

        $cancelled = $this->sessions->start($user->id, null, 'Cancelled', new DateTimeImmutable('2026-11-11T09:00:00+00:00'));
        $this->sessions->addSet($user->id, $cancelled->id(), $squat->id(), 150000, 1, new DateTimeImmutable('2026-11-11T09:02:00+00:00'));
        $cancelled->cancel(new DateTimeImmutable('2026-11-11T09:05:00+00:00'));
        $this->sessions->save($cancelled);

        // Изоляция по пользователю
        $this->assertNull($this->sessions->findOwnedById($stranger->id, $day1->id()));
        $this->assertNull($this->sessions->addSet($stranger->id, $day2->id(), $squat->id(), 50000, 5, new DateTimeImmutable('2026-11-12T09:20:00+00:00')));
        $this->assertCount(1, $this->sessions->listForClient($user->id, WorkoutSessionStatus::Completed));
        $this->assertCount(3, $this->sessions->listForClient($user->id));

        // Прогресс по приседу содержит только Day 1 и Day 2 в хронологическом порядке и только подходы по приседу
        $progress = $this->sessions->listExerciseProgress($user->id, $squat->id());
        $this->assertCount(2, $progress);
        $this->assertSame('Day 1', $progress[0]->sessionTitle);
        $this->assertCount(2, $progress[0]->sets);
        $this->assertSame('Day 2', $progress[1]->sessionTitle);
        $this->assertCount(1, $progress[1]->sets);
        $this->assertSame(90000, $progress[1]->sets[0]->weightGrams());
    }
}
