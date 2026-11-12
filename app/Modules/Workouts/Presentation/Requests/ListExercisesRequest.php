<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use App\Modules\Workouts\Domain\MuscleGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListExercisesRequest extends FormRequest
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
            'muscle_group' => ['nullable', 'string', Rule::enum(MuscleGroup::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'only_active' => ['nullable', 'boolean'],
        ];
    }
}

