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

    public function test_idempotency_key_prevents_duplicate_sessions_and_sets(): void
    {
        $client1 = User::factory()->create();
        $client2 = User::factory()->create();

        $exercises = $this->app->make(ExerciseRepositoryInterface::class);
        $bench = $exercises->create(null, 'Bench Press', MuscleGroup::CHEST);

        Sanctum::actingAs($client1);

        // Повторный старт сессии с тем же Idempotency-Key возвращает ту же сессию без дубля
        $firstStart = $this->withHeaders(['Idempotency-Key' => '  session-key-1  '])
            ->postJson('/api/v1/client/workouts/sessions', ['title' => 'Push Day']);

        $firstStart->assertCreated();
        $session1Id = (int) $firstStart->json('data.id');

        $secondStart = $this->withHeaders(['Idempotency-Key' => 'session-key-1'])
            ->postJson('/api/v1/client/workouts/sessions', ['title' => 'Push Day Retry']);

        $secondStart->assertCreated()
            ->assertJsonPath('data.id', $session1Id)
            ->assertJsonPath('data.title', 'Push Day');

        $this->assertDatabaseCount('workouts_sessions', 1);

        // Пустой/пробельный Idempotency-Key игнорируется и не склеивает запросы
        $secondBlankStart = $this->withHeaders(['Idempotency-Key' => '   '])
            ->postJson('/api/v1/client/workouts/sessions', ['title' => 'Second Session'])
            ->assertCreated();
        $session2Id = (int) $secondBlankStart->json('data.id');
        $this->assertNotSame($session1Id, $session2Id);
        $this->assertDatabaseCount('workouts_sessions', 2);

        // Повторный подход с тем же Idempotency-Key не создаёт дубль подхода
        $firstSet = $this->withHeaders(['Idempotency-Key' => 'set-key-1'])
            ->postJson("/api/v1/client/workouts/sessions/{$session1Id}/sets", [
                'exercise_id' => $bench->id(),
                'weight_grams' => 60000,
                'reps' => 10,
            ]);

        $firstSet->assertCreated()->assertJsonCount(1, 'data.sets');

        $retrySet = $this->withHeaders(['Idempotency-Key' => 'set-key-1'])
            ->postJson("/api/v1/client/workouts/sessions/{$session1Id}/sets", [
                'exercise_id' => $bench->id(),
                'weight_grams' => 65000,
                'reps' => 8,
            ]);

        $retrySet->assertCreated()
            ->assertJsonCount(1, 'data.sets')
            ->assertJsonPath('data.sets.0.weight_grams', 60000);

        $this->assertDatabaseCount('workouts_sets', 1);

        // Тот же Idempotency-Key в другой сессии создаёт свой подход
        $this->withHeaders(['Idempotency-Key' => 'set-key-1'])
            ->postJson("/api/v1/client/workouts/sessions/{$session2Id}/sets", [
                'exercise_id' => $bench->id(),
                'weight_grams' => 70000,
                'reps' => 6,
            ])
            ->assertCreated()
            ->assertJsonCount(1, 'data.sets');

        $this->assertDatabaseCount('workouts_sets', 2);

        // Тот же Idempotency-Key у другого клиента не пересекается
        Sanctum::actingAs($client2);
        $otherClientSession = $this->withHeaders(['Idempotency-Key' => 'session-key-1'])
            ->postJson('/api/v1/client/workouts/sessions', ['title' => 'Client2 Push'])
            ->assertCreated();
        $this->assertNotSame($session1Id, (int) $otherClientSession->json('data.id'));

        // Слишком длинный Idempotency-Key (> 128 символов) отклоняется с 400 BAD_REQUEST
        Sanctum::actingAs($client1);
        $tooLong = str_repeat('a', 129);
        $this->withHeaders(['Idempotency-Key' => $tooLong])
            ->postJson('/api/v1/client/workouts/sessions', ['title' => 'Too Long'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');

        $this->withHeaders(['Idempotency-Key' => $tooLong])
            ->postJson("/api/v1/client/workouts/sessions/{$session1Id}/sets", [
                'exercise_id' => $bench->id(),
                'weight_grams' => 60000,
                'reps' => 8,
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');
    }
}
