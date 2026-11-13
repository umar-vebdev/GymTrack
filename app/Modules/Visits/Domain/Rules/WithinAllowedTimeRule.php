<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;

/**
 * Если у абонемента задано окно времени суток, отметка допустима только внутри него
 * (минуты от 00:00 в часовом поясе зала, правая граница не включается). Без окна — в любое время (Бизнес-ТЗ 4.3, 5.4, 10).
 */
final class WithinAllowedTimeRule implements CheckInRuleInterface
{
    public function validate(CheckInContext $ctx): void
    {
        if ($ctx->timeFromMinute === null || $ctx->timeToMinute === null) {
            return;
        }

        $localNow = $ctx->localNow();
        $minute = (int) $localNow->format('G') * 60 + (int) $localNow->format('i');

        if ($minute < $ctx->timeFromMinute || $minute >= $ctx->timeToMinute) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_OUT_OF_TIME_WINDOW);
        }
    }
}
