<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SearchGymsRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'amenities' => ['nullable', 'array', 'max:30'],
            'amenities.*' => ['required', 'string', 'max:50'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha', 'required_with:price_min,price_max'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $priceMin = $this->input('price_min');
            $priceMax = $this->input('price_max');

            if ($priceMin !== null && $priceMax !== null && (int) $priceMin > (int) $priceMax) {
                $validator->errors()->add('price_max', 'Максимальная цена не может быть меньше минимальной.');
            }
        });
    }
}
