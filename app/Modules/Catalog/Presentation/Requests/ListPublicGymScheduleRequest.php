<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Requests;

use App\Modules\Catalog\Application\GetPublicGymSchedule;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ListPublicGymScheduleRequest extends FormRequest
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
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $utc = new DateTimeZone('UTC');
            $rawFrom = $this->input('from_date');
            $rawTo = $this->input('to_date');

            $startDay = $rawFrom !== null
                ? DateTimeImmutable::createFromFormat('!Y-m-d', (string) $rawFrom, $utc)
                : app(Clock::class)->now()->setTimezone($utc)->setTime(0, 0, 0);

            if ($startDay === false) {
                return;
            }

            $endDay = $rawTo !== null
                ? DateTimeImmutable::createFromFormat('!Y-m-d', (string) $rawTo, $utc)
                : $startDay->modify('+'.(GetPublicGymSchedule::DEFAULT_PERIOD_DAYS - 1).' days');

            if ($endDay === false) {
                return;
            }

            if ($startDay > $endDay) {
                $validator->errors()->add('to_date', 'Дата окончания периода не может быть раньше даты начала.');

                return;
            }

            $daysCount = (int) $startDay->diff($endDay)->days + 1;
            if ($daysCount > GetPublicGymSchedule::MAX_PERIOD_DAYS) {
                $validator->errors()->add(
                    'to_date',
                    sprintf('Период расписания не может превышать %d дн.', GetPublicGymSchedule::MAX_PERIOD_DAYS),
                );
            }
        });
    }
}

