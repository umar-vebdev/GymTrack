<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

final readonly class LogWorkoutSetCommand
{
    public function __construct(
        public int $userId,
        public int $sessionId,
        public int $exerciseId,
        public int $weightGrams,
        public int $reps,
    ) {}
}

