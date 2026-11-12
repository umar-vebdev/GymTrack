<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;

/**
 * Создание системного упражнения (платформа) или личного упражнения клиента (Бизнес-ТЗ 9; D-016).
 */
final class CreateExercise
{
    public function __construct(
        private readonly ExerciseRepositoryInterface $exercises,
    ) {}

    public function execute(CreateExerciseCommand $command): Exercise
    {
        return $this->exercises->create(
            userId: $command->userId,
            name: $command->name,
            muscleGroup: $command->muscleGroup,
            description: $command->description,
        );
    }
}

