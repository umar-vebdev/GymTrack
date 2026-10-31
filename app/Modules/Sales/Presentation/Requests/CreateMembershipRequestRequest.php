<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateMembershipRequestRequest extends FormRequest
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
            'gym_id' => ['required', 'integer', 'exists:gyms,id'],
            'membership_type_id' => ['required', 'integer', 'exists:membership_types,id'],
        ];
    }
}

