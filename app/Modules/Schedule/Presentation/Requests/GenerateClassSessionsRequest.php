<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation\Requests;

use App\Modules\Schedule\Application\GenerateClassSessions;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class GenerateClassSessionsRequest extends FormRequest
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
            'gym_id' => ['required', 'integer', 'min:1'],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $utc = new DateTimeZone('UTC');
                $from = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->input('from_date'), $utc);
                $to = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $this->input('to_date'), $utc);

                if ($from === false || $to === false) {
                    return;
                }

                $days = (int) $from->diff($to)->days + 1;
                if ($days > GenerateClassSessions::MAX_PERIOD_DAYS) {
                    $validator->errors()->add(
                        'to_date',
                        sprintf('Период генерации не может превышать %d дн.', GenerateClassSessions::MAX_PERIOD_DAYS),
                    );
                }
            },
        ];
    }
}
