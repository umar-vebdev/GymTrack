<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;
use App\Modules\Visits\Domain\VisitRepositoryInterface;

/**
 * Rule that ensures the client has not exceeded the daily visit limit.
 *
 * @assumption: Daily limit is hard‑coded to 2 visits. Replace with a configurable value when the domain model provides it.
 */
final class DailyLimitNotExceededRule implements CheckInRuleInterface
{
    private const int DAILY_LIMIT = 2;

    public function __construct(
        private readonly VisitRepositoryInterface $visitRepo,
    ) {}

    public function validate(CheckInContext $ctx): void
    {
        $date = $ctx->now->format('Y-m-d');
        $count = $this->visitRepo->countCompletedForClientInGymOnDate(
            $ctx->clientId,
            $ctx->gymId,
            $date,
        );

        if ($count >= self::DAILY_LIMIT) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_DAILY_LIMIT_EXCEEDED);
        }
    }
}
