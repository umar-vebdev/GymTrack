<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

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
            'gym_id' => ['required', $this->existsByIdOrPublicId('gyms')],
            'membership_type_id' => ['required', $this->existsByIdOrPublicId('membership_types')],
        ];
    }

    /**
     * @return Closure(string, mixed, Closure(string): void): void
     */
    private function existsByIdOrPublicId(string $table): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($table): void {
            if (is_int($value)) {
                $exists = $value > 0 && DB::table($table)->where('id', $value)->exists();
            } elseif (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '' || strlen($trimmed) > 26) {
                    $exists = false;
                } elseif (ctype_digit($trimmed) && strlen($trimmed) < 19) {
                    $numericId = (int) $trimmed;
                    $exists = $numericId > 0 && DB::table($table)->where('id', $numericId)->exists();
                } else {
                    $exists = DB::table($table)->where('public_id', $trimmed)->exists();
                }
            } else {
                $exists = false;
            }

            if (!$exists) {
                $fail((string) __('validation.exists', ['attribute' => $attribute]));
            }
        };
    }
}
