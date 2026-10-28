<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Memberships\Contracts\MembershipCancellerInterface;
use App\Modules\Sales\Domain\Events\SaleRefunded;
use App\Modules\Sales\Domain\Exceptions\SaleNotFound;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleRepositoryInterface;
use App\Shared\Domain\Clock;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Возврат/отмена продажи владельцем (Бизнес-ТЗ 4.2, 4.5, 4.7; Тех. ТЗ 9.2, 9.5):
 * в одной транзакции переводит продажу в статус refunded, возвращает остатки товаров на склад
 * и отменяет выданные при продаже абонементы через MembershipCancellerInterface.
 */
final class RefundSale
{
    public function __construct(
        private readonly SaleRepositoryInterface $sales,
        private readonly ProductRepositoryInterface $products,
        private readonly MembershipCancellerInterface $membershipCanceller,
        private readonly Clock $clock
    ) {}

    /**
     * @throws SaleNotFound
     * @throws DomainException
     */
    public function execute(
        int $companyId,
        int $saleId,
        string $reason,
        int $refundedByUserId
    ): Sale {
        $sale = DB::transaction(function () use ($companyId, $saleId, $reason, $refundedByUserId): Sale {
            $sale = $this->sales->findByIdForUpdate($saleId);

            if ($sale === null || $sale->companyId() !== $companyId) {
                throw SaleNotFound::withId($saleId);
            }

            $now = $this->clock->now();
            $sale->refund($reason, $refundedByUserId, $now);
            $this->sales->save($sale);

            $effectiveReason = $sale->refundReason() ?? trim($reason);

            foreach ($sale->items() as $item) {
                if ($item->productId !== null) {
                    $product = $this->products->findByIdForUpdate($item->productId);
                    if ($product !== null && $product->companyId() === $companyId) {
                        $product->incrementStock($item->quantity);
                        $this->products->save($product);
                    }
                }

                if ($item->issuedMembershipId !== null) {
                    $this->membershipCanceller->cancel(
                        companyId: $companyId,
                        membershipId: $item->issuedMembershipId,
                        reason: $effectiveReason,
                        cancelledByUserId: $refundedByUserId
                    );
                }
            }

            return $sale;
        });

        Event::dispatch(new SaleRefunded(
            saleId: $sale->id(),
            companyId: $sale->companyId(),
            clientId: $sale->clientId(),
            amount: $sale->paymentMark()->amount,
            reason: $sale->refundReason() ?? trim($reason),
            refundedByUserId: $refundedByUserId
        ));

        return $sale;
    }
}

