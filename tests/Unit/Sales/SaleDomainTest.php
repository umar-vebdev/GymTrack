<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use App\Modules\Sales\Domain\PaymentMark;
use App\Modules\Sales\Domain\PaymentMethod;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleItem;
use App\Modules\Sales\Domain\SaleStatus;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

final class SaleDomainTest extends TestCase
{
    public function test_sale_with_product_and_membership_validates_total_and_supports_refund(): void
    {
        $paidAt = new DateTimeImmutable('2026-10-28 10:00:00');
        $items = [
            SaleItem::forMembership(5, 'Monthly Pass', new Money(20000, 'TJS'), issuedMembershipId: 42),
            SaleItem::forProduct(10, 'Water', new Money(500, 'TJS'), quantity: 2),
        ];

        $mark = new PaymentMark(
            method: PaymentMethod::Cash,
            amount: new Money(21000, 'TJS'),
            acceptedByUserId: 7,
            paidAt: $paidAt
        );

        $sale = new Sale(
            id: 1,
            companyId: 10,
            clientId: 100,
            status: SaleStatus::Completed,
            paymentMark: $mark,
            items: $items
        );

        $this->assertSame(SaleStatus::Completed, $sale->status());
        $this->assertSame(21000, $sale->paymentMark()->amount->amount());
        $this->assertCount(2, $sale->items());

        $refundAt = new DateTimeImmutable('2026-10-28 12:00:00');
        $sale->refund('  Ошибочная продажа  ', 7, $refundAt);

        $this->assertSame(SaleStatus::Refunded, $sale->status());
        $this->assertSame('Ошибочная продажа', $sale->refundReason());
        $this->assertSame(7, $sale->refundedByUserId());
        $this->assertSame($refundAt, $sale->refundedAt());

        $this->expectException(DomainException::class);
        $sale->refund('Повторный возврат', 7, $refundAt);
    }

    public function test_product_only_sale_allows_null_client_id(): void
    {
        $sale = new Sale(
            id: 1,
            companyId: 10,
            clientId: null,
            status: SaleStatus::Completed,
            paymentMark: new PaymentMark(
                method: PaymentMethod::CardTerminal,
                amount: new Money(1000, 'TJS'),
                acceptedByUserId: 7,
                paidAt: new DateTimeImmutable('2026-10-28 10:00:00')
            ),
            items: [
                SaleItem::forProduct(10, 'Water', new Money(500, 'TJS'), quantity: 2),
            ]
        );

        $this->assertNull($sale->clientId());
    }

    public function test_membership_sale_requires_client_id(): void
    {
        $this->expectException(DomainException::class);

        new Sale(
            id: 1,
            companyId: 10,
            clientId: null,
            status: SaleStatus::Completed,
            paymentMark: new PaymentMark(
                method: PaymentMethod::Transfer,
                amount: new Money(20000, 'TJS'),
                acceptedByUserId: 7,
                paidAt: new DateTimeImmutable('2026-10-28 10:00:00')
            ),
            items: [
                SaleItem::forMembership(5, 'Monthly Pass', new Money(20000, 'TJS'), issuedMembershipId: 42),
            ]
        );
    }

    public function test_mismatched_payment_mark_amount_throws_exception(): void
    {
        $this->expectException(DomainException::class);

        new Sale(
            id: 1,
            companyId: 10,
            clientId: null,
            status: SaleStatus::Completed,
            paymentMark: new PaymentMark(
                method: PaymentMethod::Cash,
                amount: new Money(999, 'TJS'),
                acceptedByUserId: 7,
                paidAt: new DateTimeImmutable('2026-10-28 10:00:00')
            ),
            items: [
                SaleItem::forProduct(10, 'Water', new Money(500, 'TJS'), quantity: 2),
            ]
        );
    }
}

