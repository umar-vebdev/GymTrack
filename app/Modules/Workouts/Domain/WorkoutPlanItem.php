<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutPlan;

/**
 * Упражнение в составе тренировочного плана (Бизнес-ТЗ 9; D-016).
 */
final class WorkoutPlanItem
{
    public const TARGET_SETS_MIN = 1;

    public const TARGET_SETS_MAX = 20;

    public const TARGET_REPS_MIN = 1;

    public const TARGET_REPS_MAX = 200;

    public const REST_SECONDS_MAX = 600;

    public function __construct(
        private readonly int $id,
        private readonly int $exerciseId,
        private readonly int $sortOrder,
        private readonly int $targetSets,
        private readonly int $targetReps,
        private readonly ?int $targetWeightGrams = null,
        private readonly ?int $restSeconds = null,
    ) {
        if ($exerciseId <= 0) {
            throw InvalidWorkoutPlan::invalidExerciseId();
        }
        if ($sortOrder < 0) {
            throw InvalidWorkoutPlan::invalidSortOrder();
        }
        if ($targetSets < self::TARGET_SETS_MIN || $targetSets > self::TARGET_SETS_MAX) {
            throw InvalidWorkoutPlan::invalidTargetSets(self::TARGET_SETS_MIN, self::TARGET_SETS_MAX);
        }
        if ($targetReps < self::TARGET_REPS_MIN || $targetReps > self::TARGET_REPS_MAX) {
            throw InvalidWorkoutPlan::invalidTargetReps(self::TARGET_REPS_MIN, self::TARGET_REPS_MAX);
        }
        if ($targetWeightGrams !== null && $targetWeightGrams < 0) {
            throw InvalidWorkoutPlan::invalidTargetWeight();
        }
        if ($restSeconds !== null && ($restSeconds < 0 || $restSeconds > self::REST_SECONDS_MAX)) {
            throw InvalidWorkoutPlan::invalidRestSeconds(self::REST_SECONDS_MAX);
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function exerciseId(): int
    {
        return $this->exerciseId;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }

    public function targetSets(): int
    {
        return $this->targetSets;
    }

    public function targetReps(): int
    {
        return $this->targetReps;
    }

    public function targetWeightGrams(): ?int
    {
        return $this->targetWeightGrams;
    }

    public function restSeconds(): ?int
    {
        return $this->restSeconds;
    }
}
