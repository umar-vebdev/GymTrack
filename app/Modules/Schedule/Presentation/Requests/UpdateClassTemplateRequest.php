<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation\Requests;

use App\Modules\Schedule\Domain\ClassTemplate;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateClassTemplateRequest extends FormRequest
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
            'trainer_id' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:'.ClassTemplate::TITLE_MAX],
            'description' => ['nullable', 'string', 'max:'.ClassTemplate::DESCRIPTION_MAX],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'start_time_minute' => ['required', 'integer', 'between:0,1439'],
            'duration_minutes' => [
                'required',
                'integer',
                'between:'.ClassTemplate::MIN_DURATION_MINUTES.','.ClassTemplate::MAX_DURATION_MINUTES,
            ],
            'capacity' => [
                'required',
                'integer',
                'between:'.ClassTemplate::MIN_CAPACITY.','.ClassTemplate::MAX_CAPACITY,
            ],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
