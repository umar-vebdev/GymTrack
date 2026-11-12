<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

/**
 * Основная группа мышц упражнения (Бизнес-ТЗ 9; D-016).
 */
enum MuscleGroup: string
{
    case CHEST = 'chest';
    case BACK = 'back';
    case LEGS = 'legs';
    case SHOULDERS = 'shoulders';
    case ARMS = 'arms';
    case CORE = 'core';
    case CARDIO = 'cardio';
    case FULL_BODY = 'full_body';
}

