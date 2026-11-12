<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\WorkoutPlan;

/**
 * Представление плана с предзагруженными упражнениями без N+1 запросов.
 */
final readonly class WorkoutPlanView
{
    /**
     * @param  array<int, Exercise>  $exercisesById
     */
    public function __construct(
        public WorkoutPlan $plan,
        public array $exercisesById,
    ) {}
}

