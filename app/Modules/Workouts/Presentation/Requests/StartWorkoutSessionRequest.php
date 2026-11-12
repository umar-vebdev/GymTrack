<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use App\Modules\Workouts\Domain\WorkoutSession;
use Illuminate\Foundation\Http\FormRequest;

final class StartWorkoutSessionRequest extends FormRequest
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
            'plan_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'title' => ['required_without:plan_id', 'nullable', 'string', 'max:'.WorkoutSession::TITLE_MAX],
        ];
    }
}

