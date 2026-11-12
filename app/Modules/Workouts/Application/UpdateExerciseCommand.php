<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\MuscleGroup;

final class UpdateExerciseCommand
{
    public function __construct(
        public readonly int $exerciseId,
        public readonly ?int $userId,
        public readonly string $name,
        public readonly MuscleGroup $muscleGroup,
        public readonly ?string $description = null,
        public readonly ?bool $isActive = null,
    ) {}
}

