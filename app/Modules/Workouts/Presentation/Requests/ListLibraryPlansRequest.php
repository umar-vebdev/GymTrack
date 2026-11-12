<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use App\Modules\Workouts\Domain\PlanLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListLibraryPlansRequest extends FormRequest
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
            'level' => ['sometimes', 'nullable', 'string', Rule::enum(PlanLevel::class)],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'only_published' => ['sometimes', 'nullable', 'boolean'],
            'only_active' => ['sometimes', 'nullable', 'boolean'],
        ];
    }
}

