<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Sales\Application\Commands\RegisterSaleCommand;
use App\Modules\Sales\Domain\Events\PaymentMarked;
use App\Modules\Sales\Domain\Exceptions\ProductNotFound;
use App\Modules\Sales\Domain\PaymentMark;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleItem;
use App\Modules\Sales\Domain\SaleRepositoryInterface;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Сценарий кассы: продажа товаров и/или выдача абонементов с фиксацией отметки об оплате (PaymentMark)
 * в одной транзакции БД (Бизнес-ТЗ 4.4, 4.5; Тех. ТЗ 9.2).
 */
final class RegisterSale
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly SaleRepositoryInterface $sales,
        private readonly MembershipIssuerInterface $membershipIssuer,
        private readonly ClientLookupInterface $clients,
        private readonly Clock $clock
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(RegisterSaleCommand $command): Sale
    {
        if ($command->products === [] && $command->membershipTypeIds === []) {
            throw new DomainException('Продажа должна содержать хотя бы один товар или абонемент.');
        }

        if ($command->membershipTypeIds !== [] && $command->clientId === null) {
            throw new DomainException('Для продажи абонемента необходимо указать клиента.');
        }

        $sale = DB::transaction(function () use ($command): Sale {
            if ($command->clientId !== null && !$this->clients->existsInCompany($command->clientId, $command->companyId)) {
                throw new DomainException(sprintf('Клиент #%d не найден в этой компании.', $command->clientId));
            }

            $now = $this->clock->now();
            $items = [];
            $total = null;

            foreach ($command->products as $line) {
                $product = $this->products->findByIdForUpdate($line['product_id']);

                if ($product === null || $product->companyId() !== $command->companyId) {
                    throw ProductNotFound::withId($line['product_id']);
                }

                if (!$product->isActive()) {
                    throw new DomainException(sprintf('Товар «%s» неактивен и недоступен для продажи.', $product->name()));
                }

                $product->decrementStock($line['quantity']);
                $this->products->save($product);

                $item = SaleItem::forProduct(
                    productId: $product->id(),
                    name: $product->name(),
                    unitPrice: $product->price(),
                    quantity: $line['quantity']
                );
                $items[] = $item;
                $total = $total === null ? $item->lineTotal() : $total->add($item->lineTotal());
            }

            foreach ($command->membershipTypeIds as $typeId) {
                /** @var int $clientId */
                $clientId = $command->clientId;

                $quote = $this->membershipIssuer->quoteType($command->companyId, $typeId);
                $issuedMembershipId = $this->membershipIssuer->issue(
                    companyId: $command->companyId,
                    clientId: $clientId,
                    typeId: $typeId,
                    at: $now,
                    issuedByUserId: $command->acceptedByUserId
                );

                $item = SaleItem::forMembership(
                    membershipTypeId: $typeId,
                    name: $quote->name,
                    unitPrice: $quote->price,
                    issuedMembershipId: $issuedMembershipId
                );
                $items[] = $item;
                $total = $total === null ? $item->lineTotal() : $total->add($item->lineTotal());
            }

            $paymentMark = new PaymentMark(
                method: $command->paymentMethod,
                amount: $total ?? new Money(0, 'TJS'),
                acceptedByUserId: $command->acceptedByUserId,
                paidAt: $now
            );

            return $this->sales->create(
                companyId: $command->companyId,
                clientId: $command->clientId,
                paymentMark: $paymentMark,
                items: $items
            );
        });

        Event::dispatch(new PaymentMarked(
            saleId: $sale->id(),
            companyId: $sale->companyId(),
            clientId: $sale->clientId(),
            method: $sale->paymentMark()->method,
            amount: $sale->paymentMark()->amount,
            acceptedByUserId: $sale->paymentMark()->acceptedByUserId
        ));

        return $sale;
    }
}
