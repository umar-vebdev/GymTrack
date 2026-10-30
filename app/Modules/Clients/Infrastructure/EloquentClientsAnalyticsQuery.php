<?php

declare(strict_types=1);

namespace App\Modules\Clients\Infrastructure;

use App\Modules\Clients\Contracts\ClientsAnalyticsQueryInterface;
use App\Modules\Clients\Contracts\ClientsAnalyticsSummary;
use App\Modules\Clients\Infrastructure\Persistence\ClientModel;
use DateTimeImmutable;

final class EloquentClientsAnalyticsQuery implements ClientsAnalyticsQueryInterface
{
    public function summaryForPeriod(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ClientsAnalyticsSummary {
        $fromFormatted = $from->format('Y-m-d H:i:s');
        $toFormatted = $to->format('Y-m-d H:i:s');

        $newClients = ClientModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$fromFormatted, $toFormatted])
            ->count();

        $existingClients = ClientModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('created_at', '<', $fromFormatted)
            ->count();

        return new ClientsAnalyticsSummary(
            totalClients: $existingClients + $newClients,
            newClients: $newClients,
            existingClients: $existingClients,
        );
    }
}
