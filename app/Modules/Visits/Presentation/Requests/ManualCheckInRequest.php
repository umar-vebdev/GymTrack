<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ManualCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'gym_id' => ['required', 'integer', 'min:1'],
            'visited_at' => ['nullable', 'date'],
            'device' => ['nullable', 'string', 'max:255'],
        ];
    }
}
