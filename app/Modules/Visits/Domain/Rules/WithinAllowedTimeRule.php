<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;

/**
 * Если у абонемента задано окно времени суток, отметка допустима только внутри него
 * (минуты от 00:00 UTC, правая граница не включается). Без окна — в любое время (Бизнес-ТЗ 4.3, 5.4).
 */
final class WithinAllowedTimeRule implements CheckInRuleInterface
{
    public function validate(CheckInContext $ctx): void
    {
        if ($ctx->timeFromMinute === null || $ctx->timeToMinute === null) {
            return;
        }

        $minute = (int) $ctx->now->format('G') * 60 + (int) $ctx->now->format('i');

        if ($minute < $ctx->timeFromMinute || $minute >= $ctx->timeToMinute) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_OUT_OF_TIME_WINDOW);
        }
    }
}
