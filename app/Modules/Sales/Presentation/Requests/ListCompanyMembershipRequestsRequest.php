<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Requests;

use App\Modules\Sales\Domain\MembershipRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListCompanyMembershipRequestsRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::enum(MembershipRequestStatus::class)],
        ];
    }
}
