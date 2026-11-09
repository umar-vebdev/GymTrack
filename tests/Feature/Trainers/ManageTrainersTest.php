<?php

declare(strict_types=1);

namespace Tests\Feature\Trainers;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Trainers\Domain\Trainer;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ManageTrainersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, companyId: int, gymId: int}
     */
    private function createCompanySetup(string $suffix = 'A'): array
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$suffix}");
        $this->app->make(StaffMemberRepositoryInterface::class)->create($owner->id, $companyId, Role::OWNER);
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, "Gym {$suffix}", 'ул. Рудаки, 1');

        return [
            'owner' => $owner,
            'companyId' => $companyId,
            'gymId' => $gymId,
        ];
    }

    public function test_owner_can_create_and_update_trainer(): void
    {
        $setup = $this->createCompanySetup();

        $created = $this->actingAs($setup['owner'], 'sanctum')->postJson('/api/v1/business/trainers', [
            'gym_id' => $setup['gymId'],
            'name' => '  Иван Петров  ',
            'bio' => '  Мастер спорта  ',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.gym_id', $setup['gymId'])
            ->assertJsonPath('data.user_id', null)
            ->assertJsonPath('data.name', 'Иван Петров')
            ->assertJsonPath('data.bio', 'Мастер спорта')
            ->assertJsonPath('data.is_active', true);

        $trainerId = (int) $created->json('data.id');
        $publicId = (string) $created->json('data.public_id');
        $this->assertSame(26, strlen($publicId));

        $updated = $this->actingAs($setup['owner'], 'sanctum')->patchJson("/api/v1/business/trainers/{$trainerId}", [
            'name' => 'Иван Новый',
            'bio' => null,
            'is_active' => false,
        ]);

        $updated->assertOk()
            ->assertJsonPath('data.id', $trainerId)
            ->assertJsonPath('data.public_id', $publicId)
            ->assertJsonPath('data.name', 'Иван Новый')
            ->assertJsonPath('data.bio', null)
            ->assertJsonPath('data.is_active', false);

        $keptInactive = $this->actingAs($setup['owner'], 'sanctum')->patchJson("/api/v1/business/trainers/{$trainerId}", [
            'name' => 'Иван Обновлённый',
            'bio' => 'Кроссфит',
        ]);

        $keptInactive->assertOk()
            ->assertJsonPath('data.name', 'Иван Обновлённый')
            ->assertJsonPath('data.bio', 'Кроссфит')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_only_owner_can_create_or_update_trainers(): void
    {
        $setup = $this->createCompanySetup();
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $trainerEntity = $this->app->make(TrainerRepositoryInterface::class)->create(
            $setup['companyId'],
            $setup['gymId'],
            'Существующий',
        );

        $admin = User::factory()->create();
        $trainerUser = User::factory()->create();
        $stranger = User::factory()->create();
        $staff->create($admin->id, $setup['companyId'], Role::ADMIN);
        $staff->create($trainerUser->id, $setup['companyId'], Role::TRAINER);

        $this->postJson('/api/v1/business/trainers', [
            'gym_id' => $setup['gymId'],
            'name' => 'Гость',
        ])->assertStatus(401);

        $this->patchJson("/api/v1/business/trainers/{$trainerEntity->id()}", [
            'name' => 'Гость',
        ])->assertStatus(401);

        foreach ([$admin, $trainerUser, $stranger] as $actor) {
            $this->actingAs($actor, 'sanctum')->postJson('/api/v1/business/trainers', [
                'gym_id' => $setup['gymId'],
                'name' => 'Новый',
            ])->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');

            $this->actingAs($actor, 'sanctum')->patchJson("/api/v1/business/trainers/{$trainerEntity->id()}", [
                'name' => 'Изменённый',
            ])->assertStatus(403)->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    public function test_foreign_or_unknown_gym_and_trainer_return_404(): void
    {
        $a = $this->createCompanySetup('A');
        $b = $this->createCompanySetup('B');
        $repo = $this->app->make(TrainerRepositoryInterface::class);
        $foreignTrainer = $repo->create($b['companyId'], $b['gymId'], 'Чужой тренер');

        foreach ([$b['gymId'], 999999] as $gymId) {
            $this->actingAs($a['owner'], 'sanctum')->postJson('/api/v1/business/trainers', [
                'gym_id' => $gymId,
                'name' => 'Иван',
            ])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        }

        foreach ([$foreignTrainer->id(), 999999] as $trainerId) {
            $this->actingAs($a['owner'], 'sanctum')->patchJson("/api/v1/business/trainers/{$trainerId}", [
                'name' => 'Взлом',
            ])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');
        }

        $this->assertSame('Чужой тренер', $repo->findById($b['companyId'], $foreignTrainer->id())?->name());
    }

    public function test_invalid_inputs_return_422(): void
    {
        $setup = $this->createCompanySetup();
        $trainer = $this->app->make(TrainerRepositoryInterface::class)->create(
            $setup['companyId'],
            $setup['gymId'],
            'Иван',
        );

        $this->actingAs($setup['owner'], 'sanctum')->postJson('/api/v1/business/trainers', [
            'gym_id' => 0,
            'name' => '   ',
            'bio' => str_repeat('я', Trainer::BIO_MAX + 1),
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['gym_id', 'name', 'bio']]]);

        $this->actingAs($setup['owner'], 'sanctum')->patchJson("/api/v1/business/trainers/{$trainer->id()}", [
            'name' => str_repeat('я', Trainer::NAME_MAX + 1),
            'is_active' => 'not-a-bool',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['name', 'is_active']]]);
    }
}
