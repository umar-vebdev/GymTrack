<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListClientGymScheduleRequest extends FormRequest
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
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }
}
