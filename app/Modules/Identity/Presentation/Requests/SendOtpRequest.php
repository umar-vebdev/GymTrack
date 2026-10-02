<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SendOtpRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:20'],
        ];
    }
}
