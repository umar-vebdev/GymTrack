<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSession;
use App\Modules\Workouts\Domain\WorkoutSet;

/**
 * Общий помощник для батч-загрузки упражнений в представлениях тренировочных сессий.
 */
final class WorkoutSessionAssembler
{
    public function __construct(
        private readonly ExerciseRepositoryInterface $exercises,
    ) {}

    public function buildView(WorkoutSession $session): WorkoutSessionView
    {
        $ids = array_map(static fn (WorkoutSet $set): int => $set->exerciseId(), $session->sets());

        return new WorkoutSessionView(
            session: $session,
            exercisesById: $this->exercises->findByIds($ids),
        );
    }

    /**
     * @param  list<WorkoutSession>  $sessions
     * @return list<WorkoutSessionView>
     */
    public function buildViews(array $sessions): array
    {
        $ids = [];
        foreach ($sessions as $session) {
            foreach ($session->sets() as $set) {
                $ids[] = $set->exerciseId();
            }
        }

        $exercisesById = $this->exercises->findByIds($ids);

        return array_map(
            static fn (WorkoutSession $session): WorkoutSessionView => new WorkoutSessionView(
                session: $session,
                exercisesById: $exercisesById,
            ),
            $sessions,
        );
    }
}
