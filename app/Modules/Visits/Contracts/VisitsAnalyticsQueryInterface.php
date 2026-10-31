<?php

declare(strict_types=1);

namespace App\Modules\Visits\Contracts;

use DateTimeImmutable;

/**
 * Публичный контракт модуля Visits для аналитики посещаемости и поиска «спящих» клиентов (Бизнес-ТЗ 4.7).
 */
interface VisitsAnalyticsQueryInterface
{
    public function summaryForPeriod(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): VisitsAnalyticsSummary;

    /**
     * Уникальные ID клиентов компании, у которых было хотя бы одно завершённое посещение в интервале [$since, $until].
     *
     * @return list<int>
     */
    public function activeClientIdsBetween(
        int $companyId,
        DateTimeImmutable $since,
        DateTimeImmutable $until,
    ): array;
}
