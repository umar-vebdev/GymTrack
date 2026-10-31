<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Requests;

use App\Modules\Sales\Domain\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PayMembershipRequestRequest extends FormRequest
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
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
        ];
    }
}
