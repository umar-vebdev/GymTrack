<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure;

use App\Modules\Sales\Contracts\ItemPopularity;
use App\Modules\Sales\Contracts\RevenueSummary;
use App\Modules\Sales\Contracts\SalesAnalyticsQueryInterface;
use App\Modules\Sales\Domain\SaleStatus;
use App\Modules\Sales\Infrastructure\Persistence\SaleItemModel;
use App\Modules\Sales\Infrastructure\Persistence\SaleModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;

final class EloquentSalesAnalyticsQuery implements SalesAnalyticsQueryInterface
{
    public function revenueForPeriod(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): RevenueSummary {
        $sales = $this->completedSalesInPeriod($companyId, $from, $to);

        $totalMinor = 0;
        $membershipsMinor = 0;
        $productsMinor = 0;
        $byPaymentMethod = [
            'cash' => 0,
            'card_terminal' => 0,
            'transfer' => 0,
        ];
        $currency = 'TJS';

        foreach ($sales as $sale) {
            $currency = $sale->currency;
            $saleTotal = (int) $sale->total_minor;
            $totalMinor += $saleTotal;

            if (array_key_exists($sale->payment_method, $byPaymentMethod)) {
                $byPaymentMethod[$sale->payment_method] += $saleTotal;
            }

            foreach ($sale->items as $item) {
                $lineMinor = (int) $item->unit_price_minor * (int) $item->quantity;

                if ($item->membership_type_id !== null) {
                    $membershipsMinor += $lineMinor;
                } elseif ($item->product_id !== null) {
                    $productsMinor += $lineMinor;
                }
            }
        }

        return new RevenueSummary(
            totalMinor: $totalMinor,
            membershipsMinor: $membershipsMinor,
            productsMinor: $productsMinor,
            byPaymentMethod: $byPaymentMethod,
            salesCount: $sales->count(),
            currency: $currency,
        );
    }

    /**
     * @return list<ItemPopularity>
     */
    public function popularMemberships(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        return $this->aggregatePopularity(
            $this->completedSalesInPeriod($companyId, $from, $to),
            static fn (SaleItemModel $item): ?int => $item->membership_type_id !== null ? (int) $item->membership_type_id : null,
        );
    }

    /**
     * @return list<ItemPopularity>
     */
    public function popularProducts(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        return $this->aggregatePopularity(
            $this->completedSalesInPeriod($companyId, $from, $to),
            static fn (SaleItemModel $item): ?int => $item->product_id !== null ? (int) $item->product_id : null,
        );
    }

    /**
     * @return Collection<int, SaleModel>
     */
    private function completedSalesInPeriod(
        int $companyId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): Collection {
        return SaleModel::withoutGlobalScopes()
            ->with('items')
            ->where('company_id', $companyId)
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('paid_at', [
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s'),
            ])
            ->get();
    }

    /**
     * @param  Collection<int, SaleModel>  $sales
     * @param  callable(SaleItemModel): ?int  $idExtractor
     * @return list<ItemPopularity>
     */
    private function aggregatePopularity(Collection $sales, callable $idExtractor): array
    {
        /** @var array<int, array{name: string, soldCount: int, revenueMinor: int, currency: string}> $grouped */
        $grouped = [];

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $itemId = $idExtractor($item);
                if ($itemId === null) {
                    continue;
                }

                $qty = (int) $item->quantity;
                $lineMinor = (int) $item->unit_price_minor * $qty;

                if (!isset($grouped[$itemId])) {
                    $grouped[$itemId] = [
                        'name' => $item->name,
                        'soldCount' => 0,
                        'revenueMinor' => 0,
                        'currency' => $item->currency,
                    ];
                }

                $grouped[$itemId]['soldCount'] += $qty;
                $grouped[$itemId]['revenueMinor'] += $lineMinor;
            }
        }

        $result = [];
        foreach ($grouped as $itemId => $data) {
            $result[] = new ItemPopularity(
                itemId: $itemId,
                name: $data['name'],
                soldCount: $data['soldCount'],
                revenueMinor: $data['revenueMinor'],
                currency: $data['currency'],
            );
        }

        usort($result, static function (ItemPopularity $a, ItemPopularity $b): int {
            if ($b->soldCount !== $a->soldCount) {
                return $b->soldCount <=> $a->soldCount;
            }

            if ($b->revenueMinor !== $a->revenueMinor) {
                return $b->revenueMinor <=> $a->revenueMinor;
            }

            return $a->itemId <=> $b->itemId;
        });

        return $result;
    }
}
