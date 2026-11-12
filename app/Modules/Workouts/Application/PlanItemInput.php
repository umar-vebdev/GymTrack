<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

/**
 * Входные параметры пункта тренировочного плана (Бизнес-ТЗ 9; D-016).
 */
final readonly class PlanItemInput
{
    public function __construct(
        public int $exerciseId,
        public int $targetSets,
        public int $targetReps,
        public ?int $targetWeightGrams = null,
        public ?int $restSeconds = null,
        public ?int $sortOrder = null,
    ) {}
}

