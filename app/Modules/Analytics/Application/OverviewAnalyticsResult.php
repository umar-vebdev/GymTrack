<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Application;

use App\Modules\Clients\Contracts\ClientsAnalyticsSummary;
use App\Modules\Sales\Contracts\ItemPopularity;
use App\Modules\Sales\Contracts\RevenueSummary;
use App\Modules\Visits\Contracts\VisitsAnalyticsSummary;

final class OverviewAnalyticsResult
{
    /**
     * @param  list<ItemPopularity>  $popularMemberships
     * @param  list<ItemPopularity>  $popularProducts
     */
    public function __construct(
        public readonly RevenueSummary $revenue,
        public readonly ClientsAnalyticsSummary $clients,
        public readonly VisitsAnalyticsSummary $visits,
        public readonly int $inactiveDaysThreshold,
        public readonly int $inactiveClientsCount,
        public readonly array $popularMemberships,
        public readonly array $popularProducts,
    ) {}
}
