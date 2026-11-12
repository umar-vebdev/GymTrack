<?php

declare(strict_types=1);

namespace Tests\Feature\Workouts;

use App\Models\User;
use App\Modules\Workouts\Application\CreateExercise;
use App\Modules\Workouts\Application\CreateExerciseCommand;
use App\Modules\Workouts\Application\ListExercises;
use App\Modules\Workouts\Application\UpdateExercise;
use App\Modules\Workouts\Application\UpdateExerciseCommand;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\MuscleGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExerciseUseCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_and_clients_manage_and_isolate_exercises(): void
    {
        $clientA = User::factory()->create();
        $clientB = User::factory()->create();

        $create = $this->app->make(CreateExercise::class);
        $update = $this->app->make(UpdateExercise::class);
        $list = $this->app->make(ListExercises::class);

        $platformBench = $create->execute(new CreateExerciseCommand(
            userId: null,
            name: 'Жим лёжа',
            muscleGroup: MuscleGroup::CHEST,
            description: 'Со штангой',
        ));
        $platformPullup = $create->execute(new CreateExerciseCommand(
            userId: null,
            name: 'Подтягивания',
            muscleGroup: MuscleGroup::BACK,
        ));
        $personalA = $create->execute(new CreateExerciseCommand(
            userId: $clientA->id,
            name: 'Жим гантелей под углом',
            muscleGroup: MuscleGroup::CHEST,
        ));
        $personalB = $create->execute(new CreateExerciseCommand(
            userId: $clientB->id,
            name: 'Сгибание на бицепс',
            muscleGroup: MuscleGroup::ARMS,
        ));

        // Платформа обновляет и деактивирует своё упражнение
        $updatedPullup = $update->execute(new UpdateExerciseCommand(
            exerciseId: $platformPullup->id(),
            userId: null,
            name: 'Подтягивания широким хватом',
            muscleGroup: MuscleGroup::BACK,
            description: 'На перекладине',
            isActive: false,
        ));
        $this->assertFalse($updatedPullup->isActive());
        $this->assertSame('Подтягивания широким хватом', $updatedPullup->name());

        // Клиент А обновляет своё личное упражнение
        $updatedPersonalA = $update->execute(new UpdateExerciseCommand(
            exerciseId: $personalA->id(),
            userId: $clientA->id,
            name: 'Жим гантелей 30 градусов',
            muscleGroup: MuscleGroup::CHEST,
            description: 'Верх груди',
        ));
        $this->assertSame('Жим гантелей 30 градусов', $updatedPersonalA->name());
        $this->assertTrue($updatedPersonalA->isActive());

        // Списки и фильтры
        $this->assertCount(2, $list->forPlatform());
        $this->assertCount(1, $list->forPlatform(onlyActive: true));
        $this->assertCount(1, $list->forPlatform(MuscleGroup::BACK, 'широким', false));

        $clientAChest = $list->forClient($clientA->id, MuscleGroup::CHEST);
        $this->assertSame(['Жим гантелей 30 градусов', 'Жим лёжа'], array_map(
            static fn (Exercise $e): string => $e->name(),
            $clientAChest,
        ));

        $this->assertSame(['Жим лёжа'], array_map(
            static fn (Exercise $e): string => $e->name(),
            $list->forClient($clientB->id, MuscleGroup::CHEST),
        ));

        // Платформа не может редактировать личное упражнение клиента
        try {
            $update->execute(new UpdateExerciseCommand(
                exerciseId: $personalA->id(),
                userId: null,
                name: 'Взлом',
                muscleGroup: MuscleGroup::CHEST,
            ));
            $this->fail('Platform must not update client personal exercise.');
        } catch (WorkoutResourceNotFound) {
            $this->addToAssertionCount(1);
        }

        // Клиент Б не может редактировать ни платформенное, ни чужое упражнение
        foreach ([$platformBench->id(), $personalA->id()] as $forbiddenId) {
            try {
                $update->execute(new UpdateExerciseCommand(
                    exerciseId: $forbiddenId,
                    userId: $clientB->id,
                    name: 'Взлом',
                    muscleGroup: MuscleGroup::CHEST,
                ));
                $this->fail('Client B must not update platform or foreign exercise.');
            } catch (WorkoutResourceNotFound) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
