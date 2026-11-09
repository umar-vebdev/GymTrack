<?php

declare(strict_types=1);

namespace Tests\Feature\Trainers;

use App\Models\User;
use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Trainers\Application\CreateTrainer;
use App\Modules\Trainers\Application\CreateTrainerCommand;
use App\Modules\Trainers\Application\UpdateTrainer;
use App\Modules\Trainers\Application\UpdateTrainerCommand;
use App\Modules\Trainers\Domain\Exceptions\InvalidTrainerProfile;
use App\Modules\Trainers\Domain\Exceptions\TrainerGymNotFound;
use App\Modules\Trainers\Domain\Exceptions\TrainerNotFound;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Modules\Trainers\Infrastructure\Persistence\TrainerModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrainerUseCasesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{companyId: int, gymId: int}
     */
    private function createCompanyWithGym(string $name): array
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, "Corp {$name}");
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, "Gym {$name}", null);

        return ['companyId' => $companyId, 'gymId' => $gymId];
    }

    public function test_gym_lookup_contract_checks_company_ownership(): void
    {
        $a = $this->createCompanyWithGym('A');
        $b = $this->createCompanyWithGym('B');
        $lookup = $this->app->make(GymLookupInterface::class);

        $this->assertTrue($lookup->existsInCompany($a['gymId'], $a['companyId']));
        $this->assertFalse($lookup->existsInCompany($a['gymId'], $b['companyId']));
        $this->assertFalse($lookup->existsInCompany(999999, $a['companyId']));
    }

    public function test_create_trainer_stores_active_trainer_in_own_gym(): void
    {
        $a = $this->createCompanyWithGym('A');

        $trainer = $this->app->make(CreateTrainer::class)->execute(
            new CreateTrainerCommand($a['companyId'], $a['gymId'], ' Иван Петров ', 'Кроссфит'),
        );

        $this->assertSame('Иван Петров', $trainer->name());
        $this->assertSame('Кроссфит', $trainer->bio());
        $this->assertTrue($trainer->isActive());
        $this->assertSame($a['gymId'], $trainer->gymId());
    }

    public function test_create_trainer_rejects_foreign_or_unknown_gym_and_invalid_profile_without_rows(): void
    {
        $a = $this->createCompanyWithGym('A');
        $b = $this->createCompanyWithGym('B');
        $useCase = $this->app->make(CreateTrainer::class);

        foreach ([$b['gymId'], 999999] as $gymId) {
            try {
                $useCase->execute(new CreateTrainerCommand($a['companyId'], $gymId, 'Иван'));
                $this->fail('Foreign or unknown gym must be rejected.');
            } catch (TrainerGymNotFound) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $useCase->execute(new CreateTrainerCommand($a['companyId'], $a['gymId'], '   '));
            $this->fail('Blank name must be rejected.');
        } catch (InvalidTrainerProfile) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, TrainerModel::query()->withoutGlobalScopes()->count());
    }

    public function test_update_trainer_replaces_profile_and_controls_activity(): void
    {
        $a = $this->createCompanyWithGym('A');
        $repo = $this->app->make(TrainerRepositoryInterface::class);
        $trainer = $repo->create($a['companyId'], $a['gymId'], 'Иван', 'Старое описание');
        $useCase = $this->app->make(UpdateTrainer::class);

        $useCase->execute(new UpdateTrainerCommand($a['companyId'], $trainer->id(), 'Иван Новый', null));
        $afterRename = $repo->findById($a['companyId'], $trainer->id()) ?? $this->fail('Trainer not found');
        $this->assertSame('Иван Новый', $afterRename->name());
        $this->assertNull($afterRename->bio());
        $this->assertTrue($afterRename->isActive());

        $deactivated = $useCase->execute(new UpdateTrainerCommand($a['companyId'], $trainer->id(), 'Иван Новый', 'Био', false));
        $this->assertFalse($deactivated->isActive());

        $keptInactive = $useCase->execute(new UpdateTrainerCommand($a['companyId'], $trainer->id(), 'Иван Новый', 'Био', null));
        $this->assertFalse($keptInactive->isActive());

        $activated = $useCase->execute(new UpdateTrainerCommand($a['companyId'], $trainer->id(), 'Иван Новый', 'Био', true));
        $this->assertTrue($activated->isActive());
        $this->assertTrue($repo->findById($a['companyId'], $trainer->id())->isActive());
    }

    public function test_update_trainer_cannot_touch_foreign_or_unknown_trainer_and_keeps_data_on_invalid_input(): void
    {
        $a = $this->createCompanyWithGym('A');
        $b = $this->createCompanyWithGym('B');
        $repo = $this->app->make(TrainerRepositoryInterface::class);
        $foreign = $repo->create($b['companyId'], $b['gymId'], 'Чужой');
        $own = $repo->create($a['companyId'], $a['gymId'], 'Свой');
        $useCase = $this->app->make(UpdateTrainer::class);

        foreach ([$foreign->id(), 999999] as $trainerId) {
            try {
                $useCase->execute(new UpdateTrainerCommand($a['companyId'], $trainerId, 'Взлом'));
                $this->fail('Foreign or unknown trainer must be rejected.');
            } catch (TrainerNotFound) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame('Чужой', $repo->findById($b['companyId'], $foreign->id())?->name());

        try {
            $useCase->execute(new UpdateTrainerCommand($a['companyId'], $own->id(), '  ', null, false));
            $this->fail('Blank name must be rejected.');
        } catch (InvalidTrainerProfile) {
            $this->addToAssertionCount(1);
        }
        $unchanged = $repo->findById($a['companyId'], $own->id()) ?? $this->fail('Trainer not found');
        $this->assertSame('Свой', $unchanged->name());
        $this->assertTrue($unchanged->isActive());
    }
}
