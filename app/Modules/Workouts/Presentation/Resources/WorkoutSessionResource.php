<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Resources;

use App\Modules\Workouts\Application\WorkoutSessionView;
use App\Modules\Workouts\Domain\WorkoutSet;
use DateTimeInterface;

final class WorkoutSessionResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(WorkoutSessionView $view): array
    {
        $session = $view->session;

        return [
            'id' => $session->id(),
            'public_id' => $session->publicId(),
            'plan_id' => $session->planId(),
            'title' => $session->title(),
            'status' => $session->status()->value,
            'started_at' => $session->startedAt()->format(DateTimeInterface::ATOM),
            'finished_at' => $session->finishedAt()?->format(DateTimeInterface::ATOM),
            'notes' => $session->notes(),
            'sets' => array_map(
                static function (WorkoutSet $set) use ($view): array {
                    $exercise = $view->exercisesById[$set->exerciseId()] ?? null;

                    return [
                        'id' => $set->id(),
                        'exercise_id' => $set->exerciseId(),
                        'exercise' => $exercise !== null ? [
                            'id' => $exercise->id(),
                            'public_id' => $exercise->publicId(),
                            'name' => $exercise->name(),
                            'muscle_group' => $exercise->muscleGroup()->value,
                            'is_active' => $exercise->isActive(),
                        ] : null,
                        'set_number' => $set->setNumber(),
                        'weight_grams' => $set->weightGrams(),
                        'reps' => $set->reps(),
                        'volume_grams' => $set->volumeGrams(),
                        'performed_at' => $set->performedAt()->format(DateTimeInterface::ATOM),
                    ];
                },
                $session->sets(),
            ),
        ];
    }
}
