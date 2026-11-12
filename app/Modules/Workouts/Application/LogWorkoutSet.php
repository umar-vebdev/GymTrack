<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutSession;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;
use App\Shared\Domain\Clock;

/**
 * Фиксация подхода с весом и повторениями в активной тренировке (Бизнес-ТЗ 9; D-018).
 */
final class LogWorkoutSet
{
    public function __construct(
        private readonly WorkoutSessionRepositoryInterface $sessions,
        private readonly ExerciseRepositoryInterface $exercises,
        private readonly WorkoutSessionAssembler $assembler,
        private readonly Clock $clock,
    ) {}

    public function execute(LogWorkoutSetCommand $command): WorkoutSessionView
    {
        $exercise = $this->exercises->findByIds([$command->exerciseId])[$command->exerciseId] ?? null;

        if ($exercise === null || (!$exercise->isPlatform() && !$exercise->isOwnedBy($command->userId))) {
            throw WorkoutResourceNotFound::exercise($command->exerciseId);
        }
        if (!$exercise->isActive()) {
            throw InvalidWorkoutSession::inactiveExercise($command->exerciseId);
        }

        $updated = $this->sessions->addSet(
            userId: $command->userId,
            sessionId: $command->sessionId,
            exerciseId: $command->exerciseId,
            weightGrams: $command->weightGrams,
            reps: $command->reps,
            performedAt: $this->clock->now(),
        );

        if ($updated === null) {
            throw WorkoutResourceNotFound::session($command->sessionId);
        }

        return $this->assembler->buildView($updated);
    }
}
