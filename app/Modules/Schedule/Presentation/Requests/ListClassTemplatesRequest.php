<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListClassTemplatesRequest extends FormRequest
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
            'gym_id' => ['required', 'integer', 'min:1'],
            'only_active' => ['nullable', 'boolean'],
        ];
    }
}
