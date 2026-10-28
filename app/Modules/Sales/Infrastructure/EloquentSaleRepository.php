<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure;

use App\Modules\Sales\Domain\PaymentMark;
use App\Modules\Sales\Domain\PaymentMethod;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleItem;
use App\Modules\Sales\Domain\SaleRepositoryInterface;
use App\Modules\Sales\Domain\SaleStatus;
use App\Modules\Sales\Infrastructure\Persistence\SaleItemModel;
use App\Modules\Sales\Infrastructure\Persistence\SaleModel;
use App\Shared\Domain\Money;

final class EloquentSaleRepository implements SaleRepositoryInterface
{
    public function findById(int $id): ?Sale
    {
        $model = SaleModel::withoutGlobalScopes()->with('items')->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByIdForUpdate(int $id): ?Sale
    {
        $model = SaleModel::withoutGlobalScopes()
            ->with('items')
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function create(
        int $companyId,
        ?int $clientId,
        PaymentMark $paymentMark,
        array $items
    ): Sale {
        // Validate domain invariants before inserting rows
        new Sale(
            id: 0,
            companyId: $companyId,
            clientId: $clientId,
            status: SaleStatus::Completed,
            paymentMark: $paymentMark,
            items: $items
        );

        $saleModel = SaleModel::create([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'status' => SaleStatus::Completed->value,
            'payment_method' => $paymentMark->method->value,
            'total_minor' => $paymentMark->amount->amount(),
            'currency' => $paymentMark->amount->currency(),
            'accepted_by_user_id' => $paymentMark->acceptedByUserId,
            'paid_at' => $paymentMark->paidAt,
        ]);

        foreach ($items as $item) {
            SaleItemModel::create([
                'sale_id' => $saleModel->id,
                'product_id' => $item->productId,
                'membership_type_id' => $item->membershipTypeId,
                'issued_membership_id' => $item->issuedMembershipId,
                'name' => $item->name,
                'unit_price_minor' => $item->unitPrice->amount(),
                'currency' => $item->unitPrice->currency(),
                'quantity' => $item->quantity,
            ]);
        }

        return new Sale(
            id: $saleModel->id,
            companyId: $companyId,
            clientId: $clientId,
            status: SaleStatus::Completed,
            paymentMark: $paymentMark,
            items: $items
        );
    }

    public function listByCompany(int $companyId): array
    {
        $models = SaleModel::withoutGlobalScopes()
            ->with('items')
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->get();

        return array_values($models->map(fn (SaleModel $m): Sale => $this->toEntity($m))->all());
    }

    public function listByClient(int $companyId, int $clientId): array
    {
        $models = SaleModel::withoutGlobalScopes()
            ->with('items')
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->orderByDesc('id')
            ->get();

        return array_values($models->map(fn (SaleModel $m): Sale => $this->toEntity($m))->all());
    }

    public function save(Sale $sale): void
    {
        SaleModel::withoutGlobalScopes()
            ->whereKey($sale->id())
            ->update([
                'status' => $sale->status()->value,
                'refund_reason' => $sale->refundReason(),
                'refunded_by_user_id' => $sale->refundedByUserId(),
                'refunded_at' => $sale->refundedAt(),
            ]);
    }

    private function toEntity(SaleModel $m): Sale
    {
        $items = array_values($m->items->map(static fn (SaleItemModel $i): SaleItem => new SaleItem(
            name: $i->name,
            unitPrice: new Money($i->unit_price_minor, $i->currency),
            quantity: $i->quantity,
            productId: $i->product_id,
            membershipTypeId: $i->membership_type_id,
            issuedMembershipId: $i->issued_membership_id
        ))->all());

        return new Sale(
            id: $m->id,
            companyId: $m->company_id,
            clientId: $m->client_id,
            status: SaleStatus::from($m->status),
            paymentMark: new PaymentMark(
                method: PaymentMethod::from($m->payment_method),
                amount: new Money($m->total_minor, $m->currency),
                acceptedByUserId: $m->accepted_by_user_id,
                paidAt: $m->paid_at->toDateTimeImmutable()
            ),
            items: $items,
            refundReason: $m->refund_reason,
            refundedByUserId: $m->refunded_by_user_id,
            refundedAt: $m->refunded_at?->toDateTimeImmutable()
        );
    }
}

