<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\VisitRepositoryInterface;

/**
 * Число отметок клиента в этом зале за день не должно достигать лимита абонемента
 * (по умолчанию 1: «Вы уже отмечены сегодня», Бизнес-ТЗ 5.4, критерий 12.2).
 */
final class DailyLimitNotExceededRule implements CheckInRuleInterface
{
    public function __construct(
        private readonly VisitRepositoryInterface $visits,
    ) {}

    public function validate(CheckInContext $ctx): void
    {
        $count = $this->visits->countCompletedForClientInGymOnDate(
            $ctx->clientId,
            $ctx->gymId,
            $ctx->now->format('Y-m-d'),
        );

        if ($count >= $ctx->dailyLimit) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_DAILY_LIMIT_EXCEEDED);
        }
    }
}
