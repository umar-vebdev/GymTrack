<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;

/**
 * Правило, проверяющее, что абонемент не заморожен.
 */
final class NotFrozenRule implements CheckInRuleInterface
{
    public function validate(CheckInContext $ctx): void
    {
        if ($ctx->membershipFrozen) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_FROZEN);
        }
    }
}
