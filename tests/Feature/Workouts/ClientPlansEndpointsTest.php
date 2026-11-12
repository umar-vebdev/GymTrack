<?php

declare(strict_types=1);

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlanItem;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ClientPlansEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_manages_own_plans_and_cannot_access_strangers_plans_or_exercises(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $exercises = $this->app->make(ExerciseRepositoryInterface::class);
        $squat = $exercises->create(null, 'Squat', MuscleGroup::LEGS);
        $ownerExercise = $exercises->create($owner->id, 'Owner Fly', MuscleGroup::CHEST);
        $strangerExercise = $exercises->create($stranger->id, 'Stranger Curl', MuscleGroup::ARMS);

        Sanctum::actingAs($owner);

        // Создание собственного плана из системного и личного упражнения
        $create = $this->postJson('/api/v1/client/workouts/plans', [
            'title' => 'My Push & Legs',
            'description' => 'Morning routine',
            'level' => 'intermediate',
            'items' => [
                ['exercise_id' => $squat->id(), 'target_sets' => 4, 'target_reps' => 8, 'target_weight_grams' => 70000, 'rest_seconds' => 120],
                ['exercise_id' => $ownerExercise->id(), 'target_sets' => 3, 'target_reps' => 12],
            ],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'My Push & Legs')
            ->assertJsonPath('data.is_platform', false)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.items.0.exercise.name', 'Squat')
            ->assertJsonPath('data.items.1.exercise.name', 'Owner Fly');

        $planId = (int) $create->json('data.id');

        // Попытка добавить чужое личное упражнение отклоняется
        $this->postJson('/api/v1/client/workouts/plans', [
            'title' => 'Forbidden Exercise Plan',
            'level' => 'beginner',
            'items' => [['exercise_id' => $strangerExercise->id(), 'target_sets' => 3, 'target_reps' => 10]],
        ])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->getJson('/api/v1/client/workouts/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/v1/client/workouts/plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('data.title', 'My Push & Legs');

        // Обновление плана
        $this->patchJson("/api/v1/client/workouts/plans/{$planId}", [
            'title' => 'My Push v2',
            'description' => 'Updated',
            'level' => 'advanced',
            'items' => [['exercise_id' => $ownerExercise->id(), 'target_sets' => 5, 'target_reps' => 6]],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'My Push v2')
            ->assertJsonPath('data.level', 'advanced')
            ->assertJsonCount(1, 'data.items');

        // Изоляция между пользователями
        Sanctum::actingAs($stranger);

        $this->getJson('/api/v1/client/workouts/plans')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/client/workouts/plans/{$planId}")->assertNotFound();
        $this->patchJson("/api/v1/client/workouts/plans/{$planId}", [
            'title' => 'Hacked',
            'level' => 'beginner',
            'items' => [],
        ])->assertNotFound();
        $this->deleteJson("/api/v1/client/workouts/plans/{$planId}")->assertNotFound();

        // Мягкое архивирование владельцем
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/v1/client/workouts/plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->getJson('/api/v1/client/workouts/plans')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/client/workouts/plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_client_copies_published_library_plan_and_cannot_copy_draft_or_archived(): void
    {
        $client = User::factory()->create();
        $exercises = $this->app->make(ExerciseRepositoryInterface::class);
        $plans = $this->app->make(WorkoutPlanRepositoryInterface::class);

        $squat = $exercises->create(null, 'Squat', MuscleGroup::LEGS);

        $published = $plans->create(
            userId: null,
            sourcePlanId: null,
            title: 'Library Full Body',
            description: 'From platform',
            level: PlanLevel::Beginner,
            isPublished: true,
            items: [new WorkoutPlanItem(0, $squat->id(), 0, 4, 10, 50000, 90)],
        );
        $draft = $plans->create(null, null, 'Draft Plan', null, PlanLevel::Advanced, false, []);

        Sanctum::actingAs($client);

        $copyResponse = $this->postJson("/api/v1/client/workouts/library/{$published->id()}/copy", [
            'title' => 'My Starter Copy',
        ]);

        $copyResponse->assertCreated()
            ->assertJsonPath('data.title', 'My Starter Copy')
            ->assertJsonPath('data.is_platform', false)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.source_plan_id', $published->id())
            ->assertJsonPath('data.items.0.exercise.name', 'Squat')
            ->assertJsonPath('data.items.0.target_weight_grams', 50000);

        $this->postJson("/api/v1/client/workouts/library/{$draft->id()}/copy")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->getJson('/api/v1/client/workouts/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
