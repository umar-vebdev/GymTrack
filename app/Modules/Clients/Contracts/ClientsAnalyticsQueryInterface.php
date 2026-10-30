<?php

declare(strict_types=1);

namespace App\Modules\Clients\Contracts;

use DateTimeImmutable;

/**
 * Публичный контракт модуля Clients для аналитики: агрегированные показатели клиентской базы за период.
 */
interface ClientsAnalyticsQueryInterface
{
    public function summaryForPeriod(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): ClientsAnalyticsSummary;
}
