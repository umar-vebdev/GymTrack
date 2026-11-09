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
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng,radius_km'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat,radius_km'],
            'radius_km' => ['nullable', 'numeric', 'gt:0', 'max:100', 'required_with:lat,lng'],
            'sw_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:sw_lng,ne_lat,ne_lng'],
            'sw_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:sw_lat,ne_lat,ne_lng'],
            'ne_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:sw_lat,sw_lng,ne_lng'],
            'ne_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:sw_lat,sw_lng,ne_lat'],
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

            if ($this->filled('sw_lat') && (float) $this->input('sw_lat') > (float) $this->input('ne_lat')) {
                $validator->errors()->add('ne_lat', 'Северная граница не может быть южнее южной.');
            }

            if ($this->filled('sw_lng') && (float) $this->input('sw_lng') > (float) $this->input('ne_lng')) {
                $validator->errors()->add('ne_lng', 'Восточная граница не может быть западнее западной.');
            }
        });
    }
}
