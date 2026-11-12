<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;

/**
 * Обновление системного упражнения админом платформы или личного упражнения его владельцем (D-016).
 */
final class UpdateExercise
{
    public function __construct(
        private readonly ExerciseRepositoryInterface $exercises,
    ) {}

    public function execute(UpdateExerciseCommand $command): Exercise
    {
        $exercise = $command->userId === null
            ? $this->exercises->findPlatformById($command->exerciseId)
            : $this->exercises->findOwnedById($command->userId, $command->exerciseId);

        if ($exercise === null) {
            throw WorkoutResourceNotFound::exercise($command->exerciseId);
        }

        $exercise->update($command->name, $command->muscleGroup, $command->description);

        if ($command->isActive === true) {
            $exercise->activate();
        } elseif ($command->isActive === false) {
            $exercise->deactivate();
        }

        $this->exercises->save($exercise);

        return $exercise;
    }
}

