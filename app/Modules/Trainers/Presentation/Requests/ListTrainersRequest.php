<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListTrainersRequest extends FormRequest
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
            'gym_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
