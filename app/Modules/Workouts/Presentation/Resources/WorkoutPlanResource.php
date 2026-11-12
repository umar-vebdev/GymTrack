<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Resources;

use App\Modules\Workouts\Application\WorkoutPlanView;
use App\Modules\Workouts\Domain\WorkoutPlanItem;

final class WorkoutPlanResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(WorkoutPlanView $view): array
    {
        $plan = $view->plan;

        return [
            'id' => $plan->id(),
            'public_id' => $plan->publicId(),
            'is_platform' => $plan->isPlatform(),
            'source_plan_id' => $plan->sourcePlanId(),
            'title' => $plan->title(),
            'description' => $plan->description(),
            'level' => $plan->level()->value,
            'is_published' => $plan->isPublished(),
            'is_active' => $plan->isActive(),
            'items' => array_map(
                static function (WorkoutPlanItem $item) use ($view): array {
                    $exercise = $view->exercisesById[$item->exerciseId()] ?? null;

                    return [
                        'id' => $item->id(),
                        'exercise_id' => $item->exerciseId(),
                        'exercise' => $exercise !== null ? [
                            'id' => $exercise->id(),
                            'public_id' => $exercise->publicId(),
                            'name' => $exercise->name(),
                            'muscle_group' => $exercise->muscleGroup()->value,
                            'is_active' => $exercise->isActive(),
                        ] : null,
                        'sort_order' => $item->sortOrder(),
                        'target_sets' => $item->targetSets(),
                        'target_reps' => $item->targetReps(),
                        'target_weight_grams' => $item->targetWeightGrams(),
                        'rest_seconds' => $item->restSeconds(),
                    ];
                },
                $plan->items(),
            ),
        ];
    }
}

