<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Presentation\Requests;

use App\Modules\Trainers\Domain\Trainer;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateTrainerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:'.Trainer::NAME_MAX],
            'bio' => ['nullable', 'string', 'max:'.Trainer::BIO_MAX],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
