<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateGymRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => ['sometimes', 'nullable', 'string', 'timezone:all'],
        ];
    }
}

