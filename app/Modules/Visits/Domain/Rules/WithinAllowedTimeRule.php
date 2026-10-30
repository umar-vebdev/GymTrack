<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;

/**
 * Rule that validates the check‑in occurs within allowed gym opening hours.
 *
 * @assumption: Opening hours are fixed 06:00‑22:00 (UTC). To be replaced by a proper GymOpeningHours entity.
 */
final class WithinAllowedTimeRule implements CheckInRuleInterface
{
    private const string OPEN_HOUR = '06:00';

    private const string CLOSE_HOUR = '22:00';

    public function validate(CheckInContext $ctx): void
    {
        $time = $ctx->now->format('H:i');
        if ($time < self::OPEN_HOUR || $time >= self::CLOSE_HOUR) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_OUT_OF_TIME_WINDOW);
        }
    }
}
