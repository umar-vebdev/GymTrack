<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Application;

use App\Modules\Clients\Contracts\ClientsAnalyticsQueryInterface;
use App\Modules\Sales\Contracts\SalesAnalyticsQueryInterface;
use DateTimeImmutable;

final class GetOverviewAnalytics
{
    public function __construct(
        private readonly SalesAnalyticsQueryInterface $salesAnalytics,
        private readonly ClientsAnalyticsQueryInterface $clientsAnalytics,
    ) {}

    public function execute(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): OverviewAnalyticsResult {
        return new OverviewAnalyticsResult(
            revenue: $this->salesAnalytics->revenueForPeriod($companyId, $from, $to),
            clients: $this->clientsAnalytics->summaryForPeriod($companyId, $from, $to),
            popularMemberships: $this->salesAnalytics->popularMemberships($companyId, $from, $to),
            popularProducts: $this->salesAnalytics->popularProducts($companyId, $from, $to),
        );
    }
}
