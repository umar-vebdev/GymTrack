<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Presentation\Requests;

use App\Shared\Domain\Clock;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class AnalyticsPeriodRequest extends FormRequest
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
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'inactive_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function inactiveDays(): int
    {
        $raw = $this->validated('inactive_days');

        return $raw === null ? 14 : (int) $raw;
    }

    public function fromDate(Clock $clock): DateTimeImmutable
    {
        $from = $this->string('from')->toString();

        if ($from !== '') {
            return new DateTimeImmutable($from.' 00:00:00');
        }

        return $clock->now()->modify('first day of this month')->setTime(0, 0, 0);
    }

    public function toDate(Clock $clock): DateTimeImmutable
    {
        $to = $this->string('to')->toString();

        if ($to !== '') {
            return new DateTimeImmutable($to.' 23:59:59');
        }

        return $clock->now()->modify('last day of this month')->setTime(23, 59, 59);
    }
}
