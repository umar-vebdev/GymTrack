<?php

declare(strict_types=1);

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExercisesEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function createPlatformAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->is_platform_admin = true;
        $admin->save();

        return $admin;
    }

    public function test_platform_admin_can_create_list_filter_and_update_platform_exercises(): void
    {
        $admin = $this->createPlatformAdmin();
        $client = User::factory()->create();

        $personal = $this->app->make(ExerciseRepositoryInterface::class)
            ->create($client->id, 'Личное упражнение', MuscleGroup::ARMS);

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/platform/workouts/exercises', [
                'name' => 'Жим штанги лёжа',
                'muscle_group' => 'chest',
                'description' => 'База для грудных',
            ]);

        $create->assertStatus(201)
            ->assertJsonPath('data.is_platform', true)
            ->assertJsonPath('data.name', 'Жим штанги лёжа')
            ->assertJsonPath('data.muscle_group', 'chest')
            ->assertJsonPath('data.description', 'База для грудных')
            ->assertJsonPath('data.is_active', true);

        $exerciseId = (int) $create->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/platform/workouts/exercises', [
                'name' => 'Приседания',
                'muscle_group' => 'legs',
            ])->assertStatus(201);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/platform/workouts/exercises/{$exerciseId}", [
                'name' => 'Жим гантелей лёжа',
                'muscle_group' => 'chest',
                'description' => 'С акцентом на растяжение',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Жим гантелей лёжа')
            ->assertJsonPath('data.is_active', false);

        // В списке платформы не должно быть личных упражнений клиентов
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/platform/workouts/exercises')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/platform/workouts/exercises?muscle_group=chest&only_active=0&q=гантелей')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $exerciseId);

        // Попытка обновить личное упражнение клиента платформой -> 404
        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/platform/workouts/exercises/{$personal->id()}", [
                'name' => 'Чужое',
                'muscle_group' => 'arms',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_platform_endpoints_enforce_auth_admin_role_and_validation(): void
    {
        $regularUser = User::factory()->create();
        $admin = $this->createPlatformAdmin();

        $this->getJson('/api/v1/platform/workouts/exercises')->assertStatus(401);
        $this->postJson('/api/v1/platform/workouts/exercises', [
            'name' => 'Жим',
            'muscle_group' => 'chest',
        ])->assertStatus(401);

        $this->actingAs($regularUser, 'sanctum')
            ->getJson('/api/v1/platform/workouts/exercises')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($regularUser, 'sanctum')
            ->postJson('/api/v1/platform/workouts/exercises', [
                'name' => 'Жим',
                'muscle_group' => 'chest',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($regularUser, 'sanctum')
            ->patchJson('/api/v1/platform/workouts/exercises/1', [
                'name' => 'Жим',
                'muscle_group' => 'chest',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/platform/workouts/exercises', [
                'name' => '   ',
                'muscle_group' => 'unknown_group',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['name', 'muscle_group']]]);
    }

    public function test_client_can_list_create_and_update_own_exercises_with_tenant_isolation(): void
    {
        $clientA = User::factory()->create();
        $clientB = User::factory()->create();
        $repo = $this->app->make(ExerciseRepositoryInterface::class);

        $platformActive = $repo->create(null, 'Приседания со штангой', MuscleGroup::LEGS);
        $platformInactive = $repo->create(null, 'Устаревшее упражнение', MuscleGroup::LEGS);
        $platformInactive->deactivate();
        $repo->save($platformInactive);

        $foreignPersonal = $repo->create($clientB->id, 'Чужое упражнение', MuscleGroup::CHEST);

        $this->getJson('/api/v1/client/workouts/exercises')->assertStatus(401);
        $this->postJson('/api/v1/client/workouts/exercises', [
            'name' => 'Моё',
            'muscle_group' => 'core',
        ])->assertStatus(401);

        $created = $this->actingAs($clientA, 'sanctum')
            ->postJson('/api/v1/client/workouts/exercises', [
                'name' => 'Планка с весом',
                'muscle_group' => 'core',
                'description' => 'С диском 10 кг',
            ]);

        $created->assertStatus(201)
            ->assertJsonPath('data.is_platform', false)
            ->assertJsonPath('data.name', 'Планка с весом')
            ->assertJsonPath('data.muscle_group', 'core');

        $ownId = (int) $created->json('data.id');

        $this->actingAs($clientA, 'sanctum')
            ->getJson('/api/v1/client/workouts/exercises')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($clientA, 'sanctum')
            ->getJson('/api/v1/client/workouts/exercises?muscle_group=core&q=планка')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownId);

        // Клиент Б видит только платформенное активное, но не личное клиента А
        $this->actingAs($clientB, 'sanctum')
            ->getJson('/api/v1/client/workouts/exercises?muscle_group=core')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Обновление своего упражнения (и скрытие через is_active = false)
        $this->actingAs($clientA, 'sanctum')
            ->patchJson("/api/v1/client/workouts/exercises/{$ownId}", [
                'name' => 'Планка боковая',
                'muscle_group' => 'core',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Планка боковая')
            ->assertJsonPath('data.is_active', false);

        // После деактивации не возвращается в активном справочнике клиента
        $this->actingAs($clientA, 'sanctum')
            ->getJson('/api/v1/client/workouts/exercises?muscle_group=core')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Платформенное и чужое упражнение нельзя редактировать клиенту -> 404
        foreach ([$platformActive->id(), $foreignPersonal->id()] as $forbiddenId) {
            $this->actingAs($clientA, 'sanctum')
                ->patchJson("/api/v1/client/workouts/exercises/{$forbiddenId}", [
                    'name' => 'Попытка подмены',
                    'muscle_group' => 'legs',
                ])
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'NOT_FOUND');
        }
    }
}
