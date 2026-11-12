<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutPlan;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutPlan;
use App\Modules\Workouts\Domain\WorkoutPlanItem;

/**
 * Общий помощник для сборки пунктов плана и батч-загрузки упражнений.
 */
final class WorkoutPlanAssembler
{
    public function __construct(
        private readonly ExerciseRepositoryInterface $exercises,
    ) {}

    /**
     * Проверяет, что все упражнения — активные платформенные, и строит список пунктов плана.
     *
     * @param  list<PlanItemInput>  $inputs
     * @return list<WorkoutPlanItem>
     */
    public function resolvePlatformItems(array $inputs): array
    {
        $ids = array_map(static fn (PlanItemInput $input): int => $input->exerciseId, $inputs);
        $exercisesById = $this->exercises->findByIds($ids);

        $items = [];
        foreach ($inputs as $index => $input) {
            $exercise = $exercisesById[$input->exerciseId] ?? null;

            if ($exercise === null || !$exercise->isPlatform()) {
                throw WorkoutResourceNotFound::exercise($input->exerciseId);
            }
            if (!$exercise->isActive()) {
                throw InvalidWorkoutPlan::inactiveExercise($input->exerciseId);
            }

            $items[] = new WorkoutPlanItem(
                id: 0,
                exerciseId: $input->exerciseId,
                sortOrder: $input->sortOrder ?? $index,
                targetSets: $input->targetSets,
                targetReps: $input->targetReps,
                targetWeightGrams: $input->targetWeightGrams,
                restSeconds: $input->restSeconds,
            );
        }

        return $items;
    }

    public function buildView(WorkoutPlan $plan): WorkoutPlanView
    {
        $ids = array_map(static fn (WorkoutPlanItem $item): int => $item->exerciseId(), $plan->items());

        return new WorkoutPlanView(
            plan: $plan,
            exercisesById: $this->exercises->findByIds($ids),
        );
    }

    /**
     * @param  list<WorkoutPlan>  $plans
     * @return list<WorkoutPlanView>
     */
    public function buildViews(array $plans): array
    {
        $ids = [];
        foreach ($plans as $plan) {
            foreach ($plan->items() as $item) {
                $ids[] = $item->exerciseId();
            }
        }

        $exercisesById = $this->exercises->findByIds($ids);

        return array_map(
            static fn (WorkoutPlan $plan): WorkoutPlanView => new WorkoutPlanView(
                plan: $plan,
                exercisesById: $exercisesById,
            ),
            $plans,
        );
    }
}
