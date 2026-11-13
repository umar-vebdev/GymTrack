<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;
use App\Modules\Billing\Infrastructure\Persistence\PlatformPlanModel;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use App\Modules\Workouts\Infrastructure\Persistence\ExerciseModel;
use App\Modules\Workouts\Infrastructure\Persistence\WorkoutPlanItemModel;
use App\Modules\Workouts\Infrastructure\Persistence\WorkoutPlanModel;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_reference_data_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);

        $firstPlansCount = PlatformPlanModel::query()->count();
        $firstExercisesCount = ExerciseModel::query()->count();
        $firstWorkoutPlansCount = WorkoutPlanModel::query()->count();
        $firstPlanItemsCount = WorkoutPlanItemModel::query()->count();
        $firstPlanPublicIds = WorkoutPlanModel::query()->orderBy('id')->pluck('public_id')->all();

        $this->assertSame(5, $firstPlansCount);
        $this->assertSame(14, $firstExercisesCount);
        $this->assertSame(3, $firstWorkoutPlansCount);
        $this->assertSame(15, $firstPlanItemsCount);

        // Покрыты все группы мышц и все уровни сложности библиотеки
        $this->assertEqualsCanonicalizing(
            array_map(static fn (MuscleGroup $group): string => $group->value, MuscleGroup::cases()),
            ExerciseModel::query()->distinct()->pluck('muscle_group')->all(),
        );
        $this->assertEqualsCanonicalizing(
            array_map(static fn (PlanLevel $level): string => $level->value, PlanLevel::cases()),
            WorkoutPlanModel::query()->whereNull('user_id')->where('is_published', true)->distinct()->pluck('level')->all(),
        );

        // Повторный запуск сидера не создаёт дублей и сохраняет public_id планов
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($firstPlansCount, PlatformPlanModel::query()->count());
        $this->assertSame($firstExercisesCount, ExerciseModel::query()->count());
        $this->assertSame($firstWorkoutPlansCount, WorkoutPlanModel::query()->count());
        $this->assertSame($firstPlanItemsCount, WorkoutPlanItemModel::query()->count());
        $this->assertSame($firstPlanPublicIds, WorkoutPlanModel::query()->orderBy('id')->pluck('public_id')->all());

        // Засеянные данные корректно восстанавливаются доменными репозиториями
        /** @var PlatformPlanRepositoryInterface $platformPlans */
        $platformPlans = $this->app->make(PlatformPlanRepositoryInterface::class);
        /** @var WorkoutPlanRepositoryInterface $workoutPlans */
        $workoutPlans = $this->app->make(WorkoutPlanRepositoryInterface::class);

        $this->assertCount(5, $platformPlans->listAll(onlyActive: true));
        $libraryPlans = $workoutPlans->listLibraryForClient();
        $this->assertCount(3, $libraryPlans);
        foreach ($libraryPlans as $plan) {
            $this->assertCount(5, $plan->items());
        }
    }
}
