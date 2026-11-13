<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exercise;

/**
 * Сводка прогресса клиента по выбранному упражнению (Бизнес-ТЗ 9; D-018).
 */
final readonly class ExerciseProgressView
{
    /**
     * @param  list<ExerciseProgressPoint>  $entries
     */
    public function __construct(
        public Exercise $exercise,
        public int $sessionsCount,
        public int $bestWeightGrams,
        public int $totalSets,
        public int $totalVolumeGrams,
        public array $entries,
    ) {}
}
