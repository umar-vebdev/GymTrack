<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Application;

use App\Modules\Clients\Contracts\ClientsAnalyticsQueryInterface;
use App\Modules\Sales\Contracts\SalesAnalyticsQueryInterface;
use App\Modules\Visits\Contracts\VisitsAnalyticsQueryInterface;
use DateTimeImmutable;

final class GetOverviewAnalytics
{
    public function __construct(
        private readonly SalesAnalyticsQueryInterface $salesAnalytics,
        private readonly ClientsAnalyticsQueryInterface $clientsAnalytics,
        private readonly VisitsAnalyticsQueryInterface $visitsAnalytics,
    ) {}

    public function execute(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        int $inactiveDays = 14,
    ): OverviewAnalyticsResult {
        $clients = $this->clientsAnalytics->summaryForPeriod($companyId, $from, $to);
        $visits = $this->visitsAnalytics->summaryForPeriod($companyId, $from, $to);

        $since = $to->modify(sprintf('-%d days', $inactiveDays));
        $activeClientIds = $this->visitsAnalytics->activeClientIdsBetween($companyId, $since, $to);
        $inactiveClientsCount = max(0, $clients->totalClients - count($activeClientIds));

        return new OverviewAnalyticsResult(
            revenue: $this->salesAnalytics->revenueForPeriod($companyId, $from, $to),
            clients: $clients,
            visits: $visits,
            inactiveDaysThreshold: $inactiveDays,
            inactiveClientsCount: $inactiveClientsCount,
            popularMemberships: $this->salesAnalytics->popularMemberships($companyId, $from, $to),
            popularProducts: $this->salesAnalytics->popularProducts($companyId, $from, $to),
        );
    }
}
