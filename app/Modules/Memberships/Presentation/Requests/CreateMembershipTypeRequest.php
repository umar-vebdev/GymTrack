<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation\Requests;

use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\StartMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateMembershipTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Здесь проверяется только форма данных; бизнес-инварианты проверяет `MembershipRules`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price_minor' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'kind' => ['required', Rule::enum(MembershipKind::class)],
            'start_mode' => ['required', Rule::enum(StartMode::class)],
            'visits_count' => ['nullable', 'integer', 'min:1'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'validity_days' => ['nullable', 'integer', 'min:1'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'time_from' => ['nullable', 'date_format:H:i', 'required_with:time_to'],
            'time_to' => ['nullable', 'date_format:H:i', 'required_with:time_from'],
            'freeze_max_days' => ['nullable', 'integer', 'min:0'],
            'freeze_max_times' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
