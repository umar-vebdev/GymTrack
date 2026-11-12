<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $session_id
 * @property int $exercise_id
 * @property int $set_number
 * @property int $weight_grams
 * @property int $reps
 * @property CarbonImmutable $performed_at
 */
final class WorkoutSetModel extends Model
{
    protected $table = 'workouts_sets';

    protected $fillable = [
        'session_id',
        'exercise_id',
        'set_number',
        'weight_grams',
        'reps',
        'performed_at',
    ];

    protected $casts = [
        'session_id' => 'integer',
        'exercise_id' => 'integer',
        'set_number' => 'integer',
        'weight_grams' => 'integer',
        'reps' => 'integer',
        'performed_at' => 'immutable_datetime',
    ];
}

