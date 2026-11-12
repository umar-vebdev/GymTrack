<?php

declare(strict_types=1);

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class WorkoutSessionsEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = new DateTimeImmutable('2026-11-12T08:00:00+00:00');
        $test = $this;
        $this->app->instance(Clock::class, new class($test) implements Clock
        {
            public function __construct(private readonly WorkoutSessionsEndpointsTest $test) {}

            public function now(): DateTimeImmutable
            {
                return $this->test->currentNow();
            }
        });
    }

    public function currentNow(): DateTimeImmutable
    {
        return $this->now;
    }

    public function test_client_runs_workout_session_logs_sets_completes_and_views_progress(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $exercises = $this->app->make(ExerciseRepositoryInterface::class);
        $plans = $this->app->make(WorkoutPlanRepositoryInterface::class);

        $squat = $exercises->create(null, 'Squat', MuscleGroup::LEGS);
        $personalMove = $exercises->create($owner->id, 'Bulgarian Split', MuscleGroup::LEGS);
        $strangerMove = $exercises->create($stranger->id, 'Stranger Lift', MuscleGroup::BACK);
        $plan = $plans->create($owner->id, null, 'Heavy Legs', null, PlanLevel::Intermediate, false, []);

        Sanctum::actingAs($owner);

        // Старт по плану без передачи title берёт название плана
        $startResponse = $this->postJson('/api/v1/client/workouts/sessions', [
            'plan_id' => $plan->id(),
        ]);

        $startResponse->assertCreated()
            ->assertJsonPath('data.title', 'Heavy Legs')
            ->assertJsonPath('data.plan_id', $plan->id())
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.started_at', '2026-11-12T08:00:00+00:00');

        $session1Id = (int) $startResponse->json('data.id');

        // Фиксация подходов (системное и личное упражнение)
        $this->now = new DateTimeImmutable('2026-11-12T08:10:00+00:00');
        $this->postJson("/api/v1/client/workouts/sessions/{$session1Id}/sets", [
            'exercise_id' => $squat->id(),
            'weight_grams' => 80000,
            'reps' => 8,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sets.0.set_number', 1)
            ->assertJsonPath('data.sets.0.exercise.name', 'Squat');

        $this->now = new DateTimeImmutable('2026-11-12T08:15:00+00:00');
        $this->postJson("/api/v1/client/workouts/sessions/{$session1Id}/sets", [
            'exercise_id' => $squat->id(),
            'weight_grams' => 90000,
            'reps' => 5,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sets.1.set_number', 2)
            ->assertJsonPath('data.sets.1.volume_grams', 450000);

        $this->postJson("/api/v1/client/workouts/sessions/{$session1Id}/sets", [
            'exercise_id' => $personalMove->id(),
            'weight_grams' => 24000,
            'reps' => 10,
        ])->assertCreated()->assertJsonCount(3, 'data.sets');

        // Чужое упражнение отклоняется
        $this->postJson("/api/v1/client/workouts/sessions/{$session1Id}/sets", [
            'exercise_id' => $strangerMove->id(),
            'weight_grams' => 50000,
            'reps' => 8,
        ])->assertNotFound();

        // Завершение сессии и запрет дальнейших подходов
        $this->now = new DateTimeImmutable('2026-11-12T08:50:00+00:00');
        $this->postJson("/api/v1/client/workouts/sessions/{$session1Id}/complete", [
            'notes' => 'New squat PR',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.notes', 'New squat PR')
            ->assertJsonPath('data.finished_at', '2026-11-12T08:50:00+00:00');

        $this->postJson("/api/v1/client/workouts/sessions/{$session1Id}/sets", [
            'exercise_id' => $squat->id(),
            'weight_grams' => 95000,
            'reps' => 3,
        ])->assertStatus(400)->assertJsonPath('error.code', 'BAD_REQUEST');

        // Изоляция между пользователями
        Sanctum::actingAs($stranger);
        $this->getJson('/api/v1/client/workouts/sessions')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/client/workouts/sessions/{$session1Id}")->assertNotFound();
        $this->postJson("/api/v1/client/workouts/sessions/{$session1Id}/complete")->assertNotFound();
        $this->getJson("/api/v1/client/workouts/exercises/{$personalMove->id()}/progress")->assertNotFound();

        // Вторая сессия и проверка истории прогресса
        Sanctum::actingAs($owner);
        $this->now = new DateTimeImmutable('2026-11-14T09:00:00+00:00');
        $session2Id = (int) $this->postJson('/api/v1/client/workouts/sessions', [
            'title' => 'Quick Squat Day',
        ])->assertCreated()->json('data.id');

        $this->now = new DateTimeImmutable('2026-11-14T09:10:00+00:00');
        $this->postJson("/api/v1/client/workouts/sessions/{$session2Id}/sets", [
            'exercise_id' => $squat->id(),
            'weight_grams' => 100000,
            'reps' => 4,
        ])->assertCreated();

        $this->getJson('/api/v1/client/workouts/sessions?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/client/workouts/sessions/{$session1Id}")
            ->assertOk()
            ->assertJsonPath('data.id', $session1Id);

        $this->getJson("/api/v1/client/workouts/exercises/{$squat->id()}/progress")
            ->assertOk()
            ->assertJsonPath('data.exercise.name', 'Squat')
            ->assertJsonPath('data.sessions_count', 2)
            ->assertJsonPath('data.best_weight_grams', 100000)
            ->assertJsonPath('data.total_sets', 3)
            ->assertJsonPath('data.total_volume_grams', 1490000)
            ->assertJsonPath('data.entries.0.max_weight_grams', 90000)
            ->assertJsonPath('data.entries.1.max_weight_grams', 100000);
    }
}

