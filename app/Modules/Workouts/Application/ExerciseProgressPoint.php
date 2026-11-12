<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\WorkoutSessionStatus;
use App\Modules\Workouts\Domain\WorkoutSet;
use DateTimeImmutable;

/**
 * Точка истории прогресса по упражнению в конкретной тренировке.
 */
final readonly class ExerciseProgressPoint
{
    /**
     * @param  list<WorkoutSet>  $sets
     */
    public function __construct(
        public int $sessionId,
        public string $sessionPublicId,
        public string $sessionTitle,
        public WorkoutSessionStatus $sessionStatus,
        public DateTimeImmutable $startedAt,
        public int $setsCount,
        public int $totalReps,
        public int $maxWeightGrams,
        public int $totalVolumeGrams,
        public array $sets,
    ) {}
}

