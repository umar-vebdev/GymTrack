<?php

declare(strict_types=1);

namespace App\Modules\Sales\Contracts;

/**
 * DTO рейтинга популярности позиции продажи (типа абонемента или товара) за период.
 */
final class ItemPopularity
{
    public function __construct(
        public readonly int $itemId,
        public readonly string $name,
        public readonly int $soldCount,
        public readonly int $revenueMinor,
        public readonly string $currency = 'TJS',
    ) {}
}
