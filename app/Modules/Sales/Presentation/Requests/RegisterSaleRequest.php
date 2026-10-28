<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterSaleRequest extends FormRequest
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
            'client_id' => ['nullable', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:cash,card_terminal,transfer'],
            'products' => ['sometimes', 'array'],
            'products.*.product_id' => ['required', 'integer', 'min:1'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'membership_type_ids' => ['sometimes', 'array'],
            'membership_type_ids.*' => ['required', 'integer', 'min:1'],
        ];
    }
}
