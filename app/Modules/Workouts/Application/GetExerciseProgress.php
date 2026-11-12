<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;

/**
 * Расчёт истории и динамики прогресса клиента по упражнению (Бизнес-ТЗ 9; D-018).
 */
final class GetExerciseProgress
{
    public function __construct(
        private readonly WorkoutSessionRepositoryInterface $sessions,
        private readonly ExerciseRepositoryInterface $exercises,
    ) {}

    public function execute(int $userId, int $exerciseId): ExerciseProgressView
    {
        $exercise = $this->exercises->findByIds([$exerciseId])[$exerciseId] ?? null;

        if ($exercise === null || (!$exercise->isPlatform() && !$exercise->isOwnedBy($userId))) {
            throw WorkoutResourceNotFound::exercise($exerciseId);
        }

        $progressSessions = $this->sessions->listExerciseProgress($userId, $exerciseId);

        $entries = [];
        $bestWeight = 0;
        $totalSets = 0;
        $totalVolume = 0;

        foreach ($progressSessions as $entry) {
            $sessionMaxWeight = 0;
            $sessionTotalReps = 0;
            $sessionTotalVolume = 0;

            foreach ($entry->sets as $set) {
                $sessionTotalReps += $set->reps();
                $sessionTotalVolume += $set->volumeGrams();
                if ($set->weightGrams() > $sessionMaxWeight) {
                    $sessionMaxWeight = $set->weightGrams();
                }
            }

            if ($sessionMaxWeight > $bestWeight) {
                $bestWeight = $sessionMaxWeight;
            }
            $totalSets += count($entry->sets);
            $totalVolume += $sessionTotalVolume;

            $entries[] = new ExerciseProgressPoint(
                sessionId: $entry->sessionId,
                sessionPublicId: $entry->sessionPublicId,
                sessionTitle: $entry->sessionTitle,
                sessionStatus: $entry->sessionStatus,
                startedAt: $entry->startedAt,
                setsCount: count($entry->sets),
                totalReps: $sessionTotalReps,
                maxWeightGrams: $sessionMaxWeight,
                totalVolumeGrams: $sessionTotalVolume,
                sets: $entry->sets,
            );
        }

        return new ExerciseProgressView(
            exercise: $exercise,
            sessionsCount: count($entries),
            bestWeightGrams: $bestWeight,
            totalSets: $totalSets,
            totalVolumeGrams: $totalVolume,
            entries: $entries,
        );
    }
}

