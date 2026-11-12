<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use App\Modules\Workouts\Domain\WorkoutSessionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListWorkoutSessionsRequest extends FormRequest
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
            'status' => ['sometimes', 'nullable', 'string', Rule::enum(WorkoutSessionStatus::class)],
        ];
    }
}

