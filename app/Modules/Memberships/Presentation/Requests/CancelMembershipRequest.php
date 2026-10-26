<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge([
                'reason' => trim($this->string('reason')->toString()),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
