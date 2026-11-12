<?php

declare(strict_types=1);

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class LibraryPlansEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_manages_library_plans_and_non_admin_is_forbidden(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $client = User::factory()->create(['is_platform_admin' => false]);

        $exercises = $this->app->make(ExerciseRepositoryInterface::class);
        $squat = $exercises->create(null, 'Squat', MuscleGroup::LEGS);
        $bench = $exercises->create(null, 'Bench Press', MuscleGroup::CHEST);
        $inactive = $exercises->create(null, 'Retired Move', MuscleGroup::BACK);
        $inactive->deactivate();
        $exercises->save($inactive);
        $clientExercise = $exercises->create($client->id, 'Client Curl', MuscleGroup::ARMS);

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/platform/workouts/plans')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
        $this->postJson('/api/v1/platform/workouts/plans', [
            'title' => 'Forbidden',
            'level' => 'beginner',
        ])->assertForbidden();

        Sanctum::actingAs($admin);

        // Создание черновика с упражнениями платформы
        $createResponse = $this->postJson('/api/v1/platform/workouts/plans', [
            'title' => 'Starter Full Body',
            'description' => '3 sessions per week',
            'level' => 'beginner',
            'is_published' => false,
            'items' => [
                [
                    'exercise_id' => $squat->id(),
                    'target_sets' => 4,
                    'target_reps' => 8,
                    'target_weight_grams' => 60000,
                    'rest_seconds' => 120,
                ],
                [
                    'exercise_id' => $bench->id(),
                    'target_sets' => 3,
                    'target_reps' => 10,
                ],
            ],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.title', 'Starter Full Body')
            ->assertJsonPath('data.is_platform', true)
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.items.0.exercise.name', 'Squat')
            ->assertJsonPath('data.items.1.exercise.name', 'Bench Press');

        $planId = (int) $createResponse->json('data.id');

        // Попытка добавить личное упражнение клиента или неактивное упражнение платформы отклоняется
        $this->postJson('/api/v1/platform/workouts/plans', [
            'title' => 'Invalid Owner Exercise',
            'level' => 'beginner',
            'items' => [['exercise_id' => $clientExercise->id(), 'target_sets' => 3, 'target_reps' => 10]],
        ])->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->postJson('/api/v1/platform/workouts/plans', [
            'title' => 'Invalid Inactive Exercise',
            'level' => 'beginner',
            'items' => [['exercise_id' => $inactive->id(), 'target_sets' => 3, 'target_reps' => 10]],
        ])->assertStatus(400)->assertJsonPath('error.code', 'BAD_REQUEST');

        // Обновление и публикация
        $this->patchJson("/api/v1/platform/workouts/plans/{$planId}", [
            'title' => 'Starter Full Body Pro',
            'description' => 'Updated',
            'level' => 'intermediate',
            'is_published' => true,
            'is_active' => true,
            'items' => [
                ['exercise_id' => $bench->id(), 'target_sets' => 5, 'target_reps' => 5, 'rest_seconds' => 150],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Starter Full Body Pro')
            ->assertJsonPath('data.is_published', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.exercise.name', 'Bench Press');

        $this->getJson("/api/v1/platform/workouts/plans/{$planId}")
            ->assertOk()
            ->assertJsonPath('data.id', $planId);

        $this->getJson('/api/v1/platform/workouts/plans?only_published=1&level=intermediate&q=Pro')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/platform/workouts/plans/999999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_client_sees_only_published_active_library_plans(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $client = User::factory()->create(['is_platform_admin' => false]);

        $exercises = $this->app->make(ExerciseRepositoryInterface::class);
        $squat = $exercises->create(null, 'Squat', MuscleGroup::LEGS);

        Sanctum::actingAs($admin);

        $publishedId = (int) $this->postJson('/api/v1/platform/workouts/plans', [
            'title' => 'Beginner Legs',
            'level' => 'beginner',
            'is_published' => true,
            'items' => [['exercise_id' => $squat->id(), 'target_sets' => 4, 'target_reps' => 10]],
        ])->assertCreated()->json('data.id');

        $draftId = (int) $this->postJson('/api/v1/platform/workouts/plans', [
            'title' => 'Hidden Draft',
            'level' => 'advanced',
            'is_published' => false,
        ])->assertCreated()->json('data.id');

        $archivedId = (int) $this->postJson('/api/v1/platform/workouts/plans', [
            'title' => 'Archived Plan',
            'level' => 'beginner',
            'is_published' => true,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/platform/workouts/plans/{$archivedId}", [
            'title' => 'Archived Plan',
            'level' => 'beginner',
            'is_published' => true,
            'is_active' => false,
            'items' => [],
        ])->assertOk();

        Sanctum::actingAs($client);

        $this->getJson('/api/v1/client/workouts/library')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $publishedId)
            ->assertJsonPath('data.0.items.0.exercise.name', 'Squat');

        $this->getJson('/api/v1/client/workouts/library?level=advanced')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/client/workouts/library/{$publishedId}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Beginner Legs');

        $this->getJson("/api/v1/client/workouts/library/{$draftId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->getJson("/api/v1/client/workouts/library/{$archivedId}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
