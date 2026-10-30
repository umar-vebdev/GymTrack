<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Application;

use App\Modules\Sales\Contracts\RevenueSummary;
use App\Modules\Sales\Contracts\SalesAnalyticsQueryInterface;
use DateTimeImmutable;

final class GetRevenueReport
{
    public function __construct(
        private readonly SalesAnalyticsQueryInterface $salesAnalytics,
    ) {}

    public function execute(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): RevenueSummary {
        return $this->salesAnalytics->revenueForPeriod($companyId, $from, $to);
    }
}
