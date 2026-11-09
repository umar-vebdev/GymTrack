<?php

declare(strict_types=1);

namespace Tests\Feature\Schedule;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ManageClassTemplatesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, admin: User, companyId: int, gymId: int, trainerId: int}
     */
    private function createCompanySetup(string $suffix = 'A'): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$suffix}");
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $staff->create($owner->id, $companyId, Role::OWNER);
        $staff->create($admin->id, $companyId, Role::ADMIN);
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, "Gym {$suffix}", 'ул. Рудаки, 1');
        $trainer = $this->app->make(TrainerRepositoryInterface::class)->create($companyId, $gymId, "Trainer {$suffix}");

        return [
            'owner' => $owner,
            'admin' => $admin,
            'companyId' => $companyId,
            'gymId' => $gymId,
            'trainerId' => $trainer->id(),
        ];
    }

    public function test_owner_and_admin_can_create_and_update_class_templates(): void
    {
        $setup = $this->createCompanySetup();
        $secondTrainer = $this->app->make(TrainerRepositoryInterface::class)->create(
            $setup['companyId'],
            $setup['gymId'],
            'Second Trainer',
        );

        $created = $this->actingAs($setup['admin'], 'sanctum')->postJson('/api/v1/business/schedule/templates', [
            'gym_id' => $setup['gymId'],
            'trainer_id' => $setup['trainerId'],
            'title' => '  Утренняя йога  ',
            'description' => '  Для начинающих  ',
            'weekday' => 1,
            'start_time_minute' => 540,
            'duration_minutes' => 60,
            'capacity' => 15,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.gym_id', $setup['gymId'])
            ->assertJsonPath('data.trainer_id', $setup['trainerId'])
            ->assertJsonPath('data.title', 'Утренняя йога')
            ->assertJsonPath('data.description', 'Для начинающих')
            ->assertJsonPath('data.weekday', 1)
            ->assertJsonPath('data.start_time_minute', 540)
            ->assertJsonPath('data.duration_minutes', 60)
            ->assertJsonPath('data.capacity', 15)
            ->assertJsonPath('data.is_active', true);

        $templateId = (int) $created->json('data.id');
        $publicId = (string) $created->json('data.public_id');
        $this->assertSame(26, strlen($publicId));

        $updated = $this->actingAs($setup['owner'], 'sanctum')
            ->patchJson("/api/v1/business/schedule/templates/{$templateId}", [
                'trainer_id' => $secondTrainer->id(),
                'title' => 'Вечерний бокс',
                'description' => null,
                'weekday' => 5,
                'start_time_minute' => 1140,
                'duration_minutes' => 90,
                'capacity' => 12,
                'is_active' => false,
            ]);

        $updated->assertOk()
            ->assertJsonPath('data.id', $templateId)
            ->assertJsonPath('data.public_id', $publicId)
            ->assertJsonPath('data.trainer_id', $secondTrainer->id())
            ->assertJsonPath('data.title', 'Вечерний бокс')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.weekday', 5)
            ->assertJsonPath('data.start_time_minute', 1140)
            ->assertJsonPath('data.duration_minutes', 90)
            ->assertJsonPath('data.capacity', 12)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_guest_trainer_role_and_stranger_cannot_manage_templates(): void
    {
        $setup = $this->createCompanySetup();
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $template = $this->app->make(ClassTemplateRepositoryInterface::class)->create(
            $setup['companyId'],
            $setup['gymId'],
            $setup['trainerId'],
            'Йога',
            null,
            1,
            540,
            60,
            15,
        );

        $trainerUser = User::factory()->create();
        $stranger = User::factory()->create();
        $staff->create($trainerUser->id, $setup['companyId'], Role::TRAINER);

        $payload = [
            'gym_id' => $setup['gymId'],
            'trainer_id' => $setup['trainerId'],
            'title' => 'Йога',
            'weekday' => 1,
            'start_time_minute' => 540,
            'duration_minutes' => 60,
            'capacity' => 15,
        ];

        $this->postJson('/api/v1/business/schedule/templates', $payload)->assertStatus(401);
        $this->patchJson("/api/v1/business/schedule/templates/{$template->id()}", $payload)->assertStatus(401);

        foreach ([$trainerUser, $stranger] as $actor) {
            $this->actingAs($actor, 'sanctum')
                ->postJson('/api/v1/business/schedule/templates', $payload)
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');

            $this->actingAs($actor, 'sanctum')
                ->patchJson("/api/v1/business/schedule/templates/{$template->id()}", $payload)
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    public function test_foreign_gym_inactive_trainer_foreign_template_return_404_and_invalid_fields_return_422(): void
    {
        $a = $this->createCompanySetup('A');
        $b = $this->createCompanySetup('B');
        $trainerRepo = $this->app->make(TrainerRepositoryInterface::class);
        $inactiveTrainer = $trainerRepo->create($a['companyId'], $a['gymId'], 'Inactive');
        $inactiveTrainer->deactivate();
        $trainerRepo->save($inactiveTrainer);

        $templateRepo = $this->app->make(ClassTemplateRepositoryInterface::class);
        $foreignTemplate = $templateRepo->create(
            $b['companyId'],
            $b['gymId'],
            $b['trainerId'],
            'Чужой',
            null,
            2,
            600,
            60,
            10,
        );

        $this->actingAs($a['admin'], 'sanctum')->postJson('/api/v1/business/schedule/templates', [
            'gym_id' => $b['gymId'],
            'trainer_id' => $a['trainerId'],
            'title' => 'Йога',
            'weekday' => 1,
            'start_time_minute' => 540,
            'duration_minutes' => 60,
            'capacity' => 15,
        ])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        $this->actingAs($a['admin'], 'sanctum')->postJson('/api/v1/business/schedule/templates', [
            'gym_id' => $a['gymId'],
            'trainer_id' => $inactiveTrainer->id(),
            'title' => 'Йога',
            'weekday' => 1,
            'start_time_minute' => 540,
            'duration_minutes' => 60,
            'capacity' => 15,
        ])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        $this->actingAs($a['admin'], 'sanctum')
            ->patchJson("/api/v1/business/schedule/templates/{$foreignTemplate->id()}", [
                'trainer_id' => $a['trainerId'],
                'title' => 'Взлом',
                'weekday' => 1,
                'start_time_minute' => 540,
                'duration_minutes' => 60,
                'capacity' => 15,
            ])->assertStatus(404)->assertJsonPath('error.code', 'NOT_FOUND');

        $this->actingAs($a['admin'], 'sanctum')->postJson('/api/v1/business/schedule/templates', [
            'gym_id' => 0,
            'trainer_id' => 0,
            'title' => '   ',
            'weekday' => 8,
            'start_time_minute' => 1440,
            'duration_minutes' => 10,
            'capacity' => 0,
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure([
                'error' => [
                    'details' => [
                        'gym_id',
                        'trainer_id',
                        'title',
                        'weekday',
                        'start_time_minute',
                        'duration_minutes',
                        'capacity',
                    ],
                ],
            ]);
    }

    public function test_owner_and_admin_can_list_templates_with_optional_active_filter_and_tenant_isolation(): void
    {
        $a = $this->createCompanySetup('A');
        $b = $this->createCompanySetup('B');
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $trainerUser = User::factory()->create();
        $staff->create($trainerUser->id, $a['companyId'], Role::TRAINER);

        $repo = $this->app->make(ClassTemplateRepositoryInterface::class);
        $friday = $repo->create($a['companyId'], $a['gymId'], $a['trainerId'], 'Бокс', null, 5, 1140, 90, 12);
        $friday->deactivate();
        $repo->save($friday);

        $monday = $repo->create($a['companyId'], $a['gymId'], $a['trainerId'], 'Йога', null, 1, 540, 60, 15);
        $repo->create($b['companyId'], $b['gymId'], $b['trainerId'], 'Чужой', null, 1, 540, 60, 15);

        $this->getJson('/api/v1/business/schedule/templates?gym_id='.$a['gymId'])->assertStatus(401);

        $all = $this->actingAs($a['admin'], 'sanctum')
            ->getJson('/api/v1/business/schedule/templates?gym_id='.$a['gymId']);

        $all->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $monday->id())
            ->assertJsonPath('data.1.id', $friday->id());

        $activeOnly = $this->actingAs($a['owner'], 'sanctum')
            ->getJson('/api/v1/business/schedule/templates?gym_id='.$a['gymId'].'&only_active=1');

        $activeOnly->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $monday->id());

        $inactiveOnly = $this->actingAs($a['owner'], 'sanctum')
            ->getJson('/api/v1/business/schedule/templates?gym_id='.$a['gymId'].'&only_active=0');

        $inactiveOnly->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $friday->id());

        $this->actingAs($trainerUser, 'sanctum')
            ->getJson('/api/v1/business/schedule/templates?gym_id='.$a['gymId'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($a['admin'], 'sanctum')
            ->getJson('/api/v1/business/schedule/templates?gym_id='.$b['gymId'])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $this->actingAs($a['admin'], 'sanctum')
            ->getJson('/api/v1/business/schedule/templates')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['gym_id']]]);
    }
}
