<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $plan_id
 * @property int $exercise_id
 * @property int $sort_order
 * @property int $target_sets
 * @property int $target_reps
 * @property int|null $target_weight_grams
 * @property int|null $rest_seconds
 */
final class WorkoutPlanItemModel extends Model
{
    protected $table = 'workouts_plan_items';

    protected $fillable = [
        'plan_id',
        'exercise_id',
        'sort_order',
        'target_sets',
        'target_reps',
        'target_weight_grams',
        'rest_seconds',
    ];

    protected $casts = [
        'plan_id' => 'integer',
        'exercise_id' => 'integer',
        'sort_order' => 'integer',
        'target_sets' => 'integer',
        'target_reps' => 'integer',
        'target_weight_grams' => 'integer',
        'rest_seconds' => 'integer',
    ];
}
