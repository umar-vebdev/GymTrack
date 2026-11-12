<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use App\Modules\Workouts\Application\PlanItemInput;
use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlan;
use App\Modules\Workouts\Domain\WorkoutPlanItem;
use Illuminate\Validation\Rule;

/**
 * Общие правила валидации и преобразование входных пунктов тренировочного плана.
 */
final class WorkoutPlanRequestData
{
    /**
     * @return array<string, list<mixed>>
     */
    public static function planRules(bool $allowIsActive, bool $allowIsPublished = true): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:'.WorkoutPlan::TITLE_MAX],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.WorkoutPlan::DESCRIPTION_MAX],
            'level' => ['required', 'string', Rule::enum(PlanLevel::class)],
            'items' => ['sometimes', 'array', 'max:50'],
            'items.*.exercise_id' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'items.*.target_sets' => [
                'required',
                'integer',
                'min:'.WorkoutPlanItem::TARGET_SETS_MIN,
                'max:'.WorkoutPlanItem::TARGET_SETS_MAX,
            ],
            'items.*.target_reps' => [
                'required',
                'integer',
                'min:'.WorkoutPlanItem::TARGET_REPS_MIN,
                'max:'.WorkoutPlanItem::TARGET_REPS_MAX,
            ],
            'items.*.target_weight_grams' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'items.*.rest_seconds' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:'.WorkoutPlanItem::REST_SECONDS_MAX,
            ],
        ];

        if ($allowIsPublished) {
            $rules['is_published'] = ['sometimes', 'boolean'];
        }
        if ($allowIsActive) {
            $rules['is_active'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }

    /**
     * @param  list<array<string, mixed>>  $rawItems
     * @return list<PlanItemInput>
     */
    public static function toItemInputs(array $rawItems): array
    {
        return array_map(
            static function (array $item): PlanItemInput {
                $weight = $item['target_weight_grams'] ?? null;
                $rest = $item['rest_seconds'] ?? null;
                $sortOrder = $item['sort_order'] ?? null;

                return new PlanItemInput(
                    exerciseId: (int) $item['exercise_id'],
                    targetSets: (int) $item['target_sets'],
                    targetReps: (int) $item['target_reps'],
                    targetWeightGrams: $weight !== null ? (int) $weight : null,
                    restSeconds: $rest !== null ? (int) $rest : null,
                    sortOrder: $sortOrder !== null ? (int) $sortOrder : null,
                );
            },
            $rawItems,
        );
    }
}
