<?php

declare(strict_types=1);

namespace App\Modules\Clients\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateClientRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
        ];
    }
}
