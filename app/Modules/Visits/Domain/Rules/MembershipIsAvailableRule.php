<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;

/**
 * Абонемент должен быть пригоден: остались занятия (или безлимит) и срок не истёк (Бизнес-ТЗ 5.4).
 */
final class MembershipIsAvailableRule implements CheckInRuleInterface
{
    public function validate(CheckInContext $ctx): void
    {
        if ($ctx->remainingVisits !== null && $ctx->remainingVisits <= 0) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
        }

        if ($ctx->membershipExpiresAt !== null && $ctx->membershipExpiresAt < $ctx->now) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
        }
    }
}
