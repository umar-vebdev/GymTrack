<?php

declare(strict_types=1);

namespace App\Modules\Sales\Contracts;

use DateTimeImmutable;

/**
 * Публичный контракт модуля Sales для аналитики: чтение агрегированных показателей продаж.
 */
interface SalesAnalyticsQueryInterface
{
    public function revenueForPeriod(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): RevenueSummary;

    /**
     * @return list<ItemPopularity>
     */
    public function popularMemberships(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array;

    /**
     * @return list<ItemPopularity>
     */
    public function popularProducts(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array;
}
