<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateLibraryPlanRequest extends FormRequest
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
        return WorkoutPlanRequestData::planRules(allowIsActive: true);
    }
}
