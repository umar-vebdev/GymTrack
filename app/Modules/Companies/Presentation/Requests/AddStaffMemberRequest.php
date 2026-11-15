<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AddStaffMemberRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:admin,trainer'],
        ];
    }
}
