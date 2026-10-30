<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;

/**
 * Правило, проверяющее наличие активного абонемента.
 *
 * Требования (Бизнес‑ТЗ 5.4):
 * - Должен быть найден подходящий абонемент (candidate не null).
 * - Должно оставаться хотя бы одно посещение (remainingVisits > 0 или null – без ограничения).
 * - Дата истечения не должна быть в прошлом.
 */
final class MembershipIsAvailableRule implements CheckInRuleInterface
{
    public function validate(CheckInContext $ctx): void
    {
        $candidate = $ctx->candidate;
        if ($candidate->remainingVisits !== null && $candidate->remainingVisits <= 0) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
        }
        if ($candidate->expiresAt !== null && $candidate->expiresAt < $ctx->now) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_NO_ACTIVE_MEMBERSHIP);
        }
    }
}
