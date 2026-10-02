<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterUserRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'max:20', 'required_without:email', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
