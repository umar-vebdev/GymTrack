<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

/**
 * Уровень сложности тренировочного плана (D-016).
 */
enum PlanLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
}

