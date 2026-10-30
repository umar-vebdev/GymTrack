<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Sales\Contracts\SalesAnalyticsQueryInterface;
use App\Modules\Sales\Domain\PaymentMark;
use App\Modules\Sales\Domain\PaymentMethod;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Modules\Sales\Domain\SaleItem;
use App\Modules\Sales\Domain\SaleRepositoryInterface;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SalesAnalyticsQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_revenue_and_popularity_and_isolates_company_and_refunded_sales(): void
    {
        $staff = User::factory()->create();
        $companyA = CompanyModel::create(['owner_id' => $staff->id, 'name' => 'Gym A'])->id;
        $companyB = CompanyModel::create(['owner_id' => $staff->id, 'name' => 'Gym B'])->id;

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientA = $clients->create($companyA, 'Client A', '+992900111222');

        /** @var ProductRepositoryInterface $products */
        $products = $this->app->make(ProductRepositoryInterface::class);
        $waterId = $products->create($companyA, 'Water 0.5L', new Money(500, 'TJS'), 50);
        $barId = $products->create($companyA, 'Protein Bar', new Money(1500, 'TJS'), 50);
        $otherProduct = $products->create($companyB, 'Towel', new Money(1000, 'TJS'), 10);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $monthlyId = $types->create(
            $companyA,
            'Monthly Unlimited',
            new Money(20000, 'TJS'),
            MembershipRules::create(MembershipKind::Unlimited, StartMode::OnSale, durationDays: 30)
        );
        $singleId = $types->create(
            $companyA,
            'Single Visit',
            new Money(3000, 'TJS'),
            MembershipRules::create(MembershipKind::Single, StartMode::OnSale)
        );

        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);
        /** @var SaleRepositoryInterface $sales */
        $sales = $this->app->make(SaleRepositoryInterface::class);

        $inPeriod1 = new DateTimeImmutable('2026-10-10 12:00:00');
        $inPeriod2 = new DateTimeImmutable('2026-10-15 15:00:00');
        $beforePeriod = new DateTimeImmutable('2026-09-25 10:00:00');

        // 1. Продажа в периоде (наличные): абонемент Monthly (20000) + 2 воды (1000) = 21000
        $m1 = $issuer->issue($companyA, $clientA, $monthlyId, $inPeriod1, $staff->id);
        $sales->create(
            companyId: $companyA,
            clientId: $clientA,
            paymentMark: new PaymentMark(PaymentMethod::Cash, new Money(21000, 'TJS'), $staff->id, $inPeriod1),
            items: [
                SaleItem::forMembership($monthlyId, 'Monthly Unlimited', new Money(20000, 'TJS'), $m1),
                SaleItem::forProduct($waterId, 'Water 0.5L', new Money(500, 'TJS'), 2),
            ]
        );

        // 2. Продажа в периоде (терминал): 2 разовых абонемента + 3 батончика + 1 вода
        $m2 = $issuer->issue($companyA, $clientA, $singleId, $inPeriod2, $staff->id);
        $m3 = $issuer->issue($companyA, $clientA, $singleId, $inPeriod2, $staff->id);
        $sales->create(
            companyId: $companyA,
            clientId: $clientA,
            paymentMark: new PaymentMark(PaymentMethod::CardTerminal, new Money(11000, 'TJS'), $staff->id, $inPeriod2),
            items: [
                SaleItem::forMembership($singleId, 'Single Visit', new Money(3000, 'TJS'), $m2),
                SaleItem::forMembership($singleId, 'Single Visit', new Money(3000, 'TJS'), $m3),
                SaleItem::forProduct($barId, 'Protein Bar', new Money(1500, 'TJS'), 3),
                SaleItem::forProduct($waterId, 'Water 0.5L', new Money(500, 'TJS'), 1),
            ]
        );

        // 3. Возвращённая продажа в периоде (не должна учитываться в выручке и популярности)
        $refundedSale = $sales->create(
            companyId: $companyA,
            clientId: null,
            paymentMark: new PaymentMark(PaymentMethod::Transfer, new Money(1500, 'TJS'), $staff->id, $inPeriod2),
            items: [
                SaleItem::forProduct($barId, 'Protein Bar', new Money(1500, 'TJS'), 1),
            ]
        );
        $refundedSale->refund('Отмена', $staff->id, new DateTimeImmutable('2026-10-15 16:00:00'));
        $sales->save($refundedSale);

        // 4. Продажа вне выбранного периода
        $sales->create(
            companyId: $companyA,
            clientId: null,
            paymentMark: new PaymentMark(PaymentMethod::Cash, new Money(500, 'TJS'), $staff->id, $beforePeriod),
            items: [
                SaleItem::forProduct($waterId, 'Water 0.5L', new Money(500, 'TJS'), 1),
            ]
        );

        // 5. Продажа другой компании в том же периоде
        $sales->create(
            companyId: $companyB,
            clientId: null,
            paymentMark: new PaymentMark(PaymentMethod::Cash, new Money(1000, 'TJS'), $staff->id, $inPeriod1),
            items: [
                SaleItem::forProduct($otherProduct, 'Towel', new Money(1000, 'TJS'), 1),
            ]
        );

        /** @var SalesAnalyticsQueryInterface $analytics */
        $analytics = $this->app->make(SalesAnalyticsQueryInterface::class);

        $from = new DateTimeImmutable('2026-10-01 00:00:00');
        $to = new DateTimeImmutable('2026-10-31 23:59:59');

        $revenue = $analytics->revenueForPeriod($companyA, $from, $to);

        $this->assertSame(32000, $revenue->totalMinor);
        $this->assertSame(26000, $revenue->membershipsMinor);
        $this->assertSame(6000, $revenue->productsMinor);
        $this->assertSame(2, $revenue->salesCount);
        $this->assertSame([
            'cash' => 21000,
            'card_terminal' => 11000,
            'transfer' => 0,
        ], $revenue->byPaymentMethod);

        $popularMemberships = $analytics->popularMemberships($companyA, $from, $to);
        $this->assertCount(2, $popularMemberships);
        $this->assertSame($singleId, $popularMemberships[0]->itemId);
        $this->assertSame(2, $popularMemberships[0]->soldCount);
        $this->assertSame(6000, $popularMemberships[0]->revenueMinor);
        $this->assertSame($monthlyId, $popularMemberships[1]->itemId);
        $this->assertSame(1, $popularMemberships[1]->soldCount);
        $this->assertSame(20000, $popularMemberships[1]->revenueMinor);

        $popularProducts = $analytics->popularProducts($companyA, $from, $to);
        $this->assertCount(2, $popularProducts);
        // Оба товара проданы по 3 штуки, но Protein Bar дал больше выручки (4500 > 1500)
        $this->assertSame($barId, $popularProducts[0]->itemId);
        $this->assertSame(3, $popularProducts[0]->soldCount);
        $this->assertSame(4500, $popularProducts[0]->revenueMinor);
        $this->assertSame($waterId, $popularProducts[1]->itemId);
        $this->assertSame(3, $popularProducts[1]->soldCount);
        $this->assertSame(1500, $popularProducts[1]->revenueMinor);
    }
}
