<?php

declare(strict_types=1);

namespace App\Modules\Visits\Contracts;

/**
 * DTO со сводкой посещаемости компании за выбранный период (Бизнес-ТЗ 4.7).
 */
final class VisitsAnalyticsSummary
{
    /**
     * @param  array<string, int>  $byDay  Количество посещений по датам (YYYY-MM-DD => count)
     */
    public function __construct(
        public readonly int $totalVisits,
        public readonly int $qrVisits,
        public readonly int $manualVisits,
        public readonly array $byDay,
    ) {}
}
