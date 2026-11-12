<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\MuscleGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:'.Exercise::NAME_MAX],
            'muscle_group' => ['required', 'string', Rule::enum(MuscleGroup::class)],
            'description' => ['nullable', 'string', 'max:'.Exercise::DESCRIPTION_MAX],
        ];
    }
}
