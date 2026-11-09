<?php

declare(strict_types=1);

namespace Tests\Feature\Trainers;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListAndShowTrainersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, admin: User, companyId: int, gymId: int}
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

        return [
            'owner' => $owner,
            'admin' => $admin,
            'companyId' => $companyId,
            'gymId' => $gymId,
        ];
    }

    public function test_owner_and_admin_can_list_gym_trainers_and_view_trainer_card(): void
    {
        $setup = $this->createCompanySetup();
        $secondGymId = $this->app->make(GymRepositoryInterface::class)->create(
            $setup['companyId'],
            'Second Gym',
            null,
        );
        $repo = $this->app->make(TrainerRepositoryInterface::class);

        $zed = $repo->create($setup['companyId'], $setup['gymId'], 'Зубов Пётр', 'Бокс');
        $zed->deactivate();
        $repo->save($zed);

        $alpha = $repo->create($setup['companyId'], $setup['gymId'], 'Антонов Илья', 'Йога');
        $repo->create($setup['companyId'], $secondGymId, 'Другой филиал');

        foreach ([$setup['owner'], $setup['admin']] as $actor) {
            $list = $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/business/trainers?gym_id='.$setup['gymId']);

            $list->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('data.0.id', $alpha->id())
                ->assertJsonPath('data.0.name', 'Антонов Илья')
                ->assertJsonPath('data.0.is_active', true)
                ->assertJsonPath('data.1.id', $zed->id())
                ->assertJsonPath('data.1.name', 'Зубов Пётр')
                ->assertJsonPath('data.1.is_active', false);

            $card = $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/business/trainers/'.$zed->id());

            $card->assertOk()
                ->assertJsonPath('data.id', $zed->id())
                ->assertJsonPath('data.public_id', $zed->publicId())
                ->assertJsonPath('data.gym_id', $setup['gymId'])
                ->assertJsonPath('data.name', 'Зубов Пётр')
                ->assertJsonPath('data.bio', 'Бокс')
                ->assertJsonPath('data.is_active', false);
        }
    }

    public function test_guest_trainer_role_and_stranger_cannot_list_or_view_trainers(): void
    {
        $setup = $this->createCompanySetup();
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);
        $trainerEntity = $this->app->make(TrainerRepositoryInterface::class)->create(
            $setup['companyId'],
            $setup['gymId'],
            'Иван',
        );

        $trainerUser = User::factory()->create();
        $stranger = User::factory()->create();
        $staff->create($trainerUser->id, $setup['companyId'], Role::TRAINER);

        $this->getJson('/api/v1/business/trainers?gym_id='.$setup['gymId'])->assertStatus(401);
        $this->getJson('/api/v1/business/trainers/'.$trainerEntity->id())->assertStatus(401);

        foreach ([$trainerUser, $stranger] as $actor) {
            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/business/trainers?gym_id='.$setup['gymId'])
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');

            $this->actingAs($actor, 'sanctum')
                ->getJson('/api/v1/business/trainers/'.$trainerEntity->id())
                ->assertStatus(403)
                ->assertJsonPath('error.code', 'FORBIDDEN');
        }
    }

    public function test_tenant_isolation_and_validation_on_list_and_show(): void
    {
        $a = $this->createCompanySetup('A');
        $b = $this->createCompanySetup('B');
        $repo = $this->app->make(TrainerRepositoryInterface::class);
        $foreignTrainer = $repo->create($b['companyId'], $b['gymId'], 'Чужой тренер');

        foreach ([$b['gymId'], 999999] as $gymId) {
            $this->actingAs($a['admin'], 'sanctum')
                ->getJson('/api/v1/business/trainers?gym_id='.$gymId)
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'NOT_FOUND');
        }

        foreach ([$foreignTrainer->id(), 999999] as $trainerId) {
            $this->actingAs($a['admin'], 'sanctum')
                ->getJson('/api/v1/business/trainers/'.$trainerId)
                ->assertStatus(404)
                ->assertJsonPath('error.code', 'NOT_FOUND');
        }

        $this->actingAs($a['admin'], 'sanctum')
            ->getJson('/api/v1/business/trainers')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['gym_id']]]);

        $this->actingAs($a['admin'], 'sanctum')
            ->getJson('/api/v1/business/trainers?gym_id=0')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['gym_id']]]);
    }
}
