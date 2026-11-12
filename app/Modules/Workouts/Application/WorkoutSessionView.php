<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\WorkoutSession;

/**
 * Представление тренировочной сессии с предзагруженными упражнениями без N+1 запросов.
 */
final readonly class WorkoutSessionView
{
    /**
     * @param  array<int, Exercise>  $exercisesById
     */
    public function __construct(
        public WorkoutSession $session,
        public array $exercisesById,
    ) {}
}

