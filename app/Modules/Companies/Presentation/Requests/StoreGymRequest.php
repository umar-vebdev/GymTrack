<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreGymRequest extends FormRequest
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
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'geo_check_enabled' => ['sometimes', 'boolean'],
            'geo_check_radius_meters' => ['sometimes', 'integer', 'min:50', 'max:5000'],
        ];
    }
}
