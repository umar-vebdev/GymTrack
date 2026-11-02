<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListMyPurchasesRequest extends FormRequest
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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'company_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:completed,refunded'],
        ];
    }
}

