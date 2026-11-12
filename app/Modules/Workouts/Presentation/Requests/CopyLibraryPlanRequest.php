<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use App\Modules\Workouts\Domain\WorkoutPlan;
use Illuminate\Foundation\Http\FormRequest;

final class CopyLibraryPlanRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:'.WorkoutPlan::TITLE_MAX],
        ];
    }
}
