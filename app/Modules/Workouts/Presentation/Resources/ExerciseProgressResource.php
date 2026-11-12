<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Resources;

use App\Modules\Workouts\Application\ExerciseProgressPoint;
use App\Modules\Workouts\Application\ExerciseProgressView;
use App\Modules\Workouts\Domain\WorkoutSet;
use DateTimeInterface;

final class ExerciseProgressResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ExerciseProgressView $view): array
    {
        return [
            'exercise' => ExerciseResource::toArray($view->exercise),
            'sessions_count' => $view->sessionsCount,
            'best_weight_grams' => $view->bestWeightGrams,
            'total_sets' => $view->totalSets,
            'total_volume_grams' => $view->totalVolumeGrams,
            'entries' => array_map(
                static fn (ExerciseProgressPoint $point): array => [
                    'session_id' => $point->sessionId,
                    'session_public_id' => $point->sessionPublicId,
                    'session_title' => $point->sessionTitle,
                    'session_status' => $point->sessionStatus->value,
                    'started_at' => $point->startedAt->format(DateTimeInterface::ATOM),
                    'sets_count' => $point->setsCount,
                    'total_reps' => $point->totalReps,
                    'max_weight_grams' => $point->maxWeightGrams,
                    'total_volume_grams' => $point->totalVolumeGrams,
                    'sets' => array_map(
                        static fn (WorkoutSet $set): array => [
                            'id' => $set->id(),
                            'set_number' => $set->setNumber(),
                            'weight_grams' => $set->weightGrams(),
                            'reps' => $set->reps(),
                            'volume_grams' => $set->volumeGrams(),
                            'performed_at' => $set->performedAt()->format(DateTimeInterface::ATOM),
                        ],
                        $point->sets,
                    ),
                ],
                $view->entries,
            ),
        ];
    }
}

