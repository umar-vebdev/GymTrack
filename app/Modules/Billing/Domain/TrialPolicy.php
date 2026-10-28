<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Правило расчёта бесплатного пробного периода для новой компании (Бизнес-ТЗ 1.1, 4.1: по умолчанию 14 дней).
 */
final class TrialPolicy
{
    public const int DEFAULT_TRIAL_DAYS = 14;

    public static function trialEndsAt(
        DateTimeImmutable $startedAt,
        int $days = self::DEFAULT_TRIAL_DAYS
    ): DateTimeImmutable {
        if ($days < 1) {
            throw new DomainException('Длительность пробного периода должна быть не менее 1 дня.');
        }

        return $startedAt->modify(sprintf('+%d days', $days));
    }
}

