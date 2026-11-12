<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Presentation\Requests;

use App\Modules\Workouts\Domain\WorkoutSession;
use Illuminate\Foundation\Http\FormRequest;

final class CompleteWorkoutSessionRequest extends FormRequest
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
            'cancel' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.WorkoutSession::NOTES_MAX],
        ];
    }
}

