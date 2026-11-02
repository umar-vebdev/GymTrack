<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterDeviceTokenRequest extends FormRequest
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
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios'],
        ];
    }
}
