<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListMyMembershipsRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'in:active,past'],
            'company_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
