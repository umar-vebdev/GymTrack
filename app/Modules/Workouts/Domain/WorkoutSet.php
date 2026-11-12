<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutSession;
use DateTimeImmutable;

/**
 * Зафиксированный подход в рамках тренировочной сессии (Бизнес-ТЗ 9; D-018).
 */
final class WorkoutSet
{
    public const REPS_MIN = 1;

    public const REPS_MAX = 500;

    public function __construct(
        private readonly int $id,
        private readonly int $exerciseId,
        private readonly int $setNumber,
        private readonly int $weightGrams,
        private readonly int $reps,
        private readonly DateTimeImmutable $performedAt,
    ) {
        if ($exerciseId <= 0) {
            throw InvalidWorkoutSession::invalidExerciseId();
        }
        if ($setNumber < 1) {
            throw InvalidWorkoutSession::invalidSetNumber();
        }
        if ($weightGrams < 0) {
            throw InvalidWorkoutSession::invalidWeightGrams();
        }
        if ($reps < self::REPS_MIN || $reps > self::REPS_MAX) {
            throw InvalidWorkoutSession::invalidReps(self::REPS_MIN, self::REPS_MAX);
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

    public function setNumber(): int
    {
        return $this->setNumber;
    }

    public function weightGrams(): int
    {
        return $this->weightGrams;
    }

    public function reps(): int
    {
        return $this->reps;
    }

    public function performedAt(): DateTimeImmutable
    {
        return $this->performedAt;
    }

    public function volumeGrams(): int
    {
        return $this->weightGrams * $this->reps;
    }
}
