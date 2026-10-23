<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SetMembershipTypeActiveRequest extends FormRequest
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
            'is_active' => ['required', 'boolean'],
        ];
    }
}
