<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

/**
 * Статус тренировочной сессии в дневнике (Бизнес-ТЗ 9; D-018).
 */
enum WorkoutSessionStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

