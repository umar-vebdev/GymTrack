<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateGymProfileRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'phone' => ['nullable', 'string', 'max:32'],
            'working_hours' => ['nullable', 'array'],
            'amenities' => ['nullable', 'array', 'max:30'],
            'amenities.*' => ['string', 'max:50'],
        ];
    }
}

