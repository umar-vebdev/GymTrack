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
}
