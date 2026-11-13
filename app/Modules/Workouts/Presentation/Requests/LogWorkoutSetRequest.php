<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use App\Modules\Workouts\Domain\WorkoutSet;
use Illuminate\Foundation\Http\FormRequest;

final class LogWorkoutSetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'exercise_id' => ['required', 'integer', 'min:1'],
            'weight_grams' => ['required', 'integer', 'min:0'],
            'reps' => ['required', 'integer', 'min:'.WorkoutSet::REPS_MIN, 'max:'.WorkoutSet::REPS_MAX],
        ];
    }
}
