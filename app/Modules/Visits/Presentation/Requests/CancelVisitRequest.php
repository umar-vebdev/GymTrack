<?php

declare(strict_types=1);

namespace App\Modules\Visits\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelVisitRequest extends FormRequest
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
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}

