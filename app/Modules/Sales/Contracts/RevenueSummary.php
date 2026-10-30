<?php

declare(strict_types=1);

namespace App\Modules\Sales\Contracts;

/**
 * DTO со сводкой выручки по отметкам оплаты за выбранный период.
 */
final class RevenueSummary
{
    /**
     * @param  array{cash: int, card_terminal: int, transfer: int}  $byPaymentMethod
     */
    public function __construct(
        public readonly int $totalMinor,
        public readonly int $membershipsMinor,
        public readonly int $productsMinor,
        public readonly array $byPaymentMethod,
        public readonly int $salesCount,
        public readonly string $currency = 'TJS',
    ) {}
}
