<?php

declare(strict_types=1);

namespace App\Modules\Visits\Infrastructure;

use App\Modules\Visits\Contracts\VisitsAnalyticsQueryInterface;
use App\Modules\Visits\Contracts\VisitsAnalyticsSummary;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitStatus;
use App\Modules\Visits\Infrastructure\Persistence\VisitModel;
use DateTimeImmutable;

final class EloquentVisitsAnalyticsQuery implements VisitsAnalyticsQueryInterface
{
    public function summaryForPeriod(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): VisitsAnalyticsSummary {
        $rows = VisitModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('status', VisitStatus::Completed->value)
            ->whereBetween('visited_at', [
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s'),
            ])
            ->get(['method', 'visit_date']);

        $qrVisits = 0;
        $manualVisits = 0;
        /** @var array<string, int> $byDay */
        $byDay = [];

        foreach ($rows as $row) {
            if ($row->method === VisitMethod::Qr->value) {
                $qrVisits++;
            } else {
                $manualVisits++;
            }

            $day = (string) $row->visit_date;
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;
        }

        ksort($byDay);

        return new VisitsAnalyticsSummary(
            totalVisits: $qrVisits + $manualVisits,
            qrVisits: $qrVisits,
            manualVisits: $manualVisits,
            byDay: $byDay,
        );
    }

    public function activeClientIdsBetween(
        int $companyId,
        DateTimeImmutable $since,
        DateTimeImmutable $until,
    ): array {
        /** @var list<int> $ids */
        $ids = array_values(
            VisitModel::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('status', VisitStatus::Completed->value)
                ->whereBetween('visited_at', [
                    $since->format('Y-m-d H:i:s'),
                    $until->format('Y-m-d H:i:s'),
                ])
                ->distinct()
                ->pluck('client_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all()
        );

        return $ids;
    }
}

