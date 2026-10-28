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
use App\Modules\Sales\Domain\PaymentMark;
use App\Modules\Sales\Domain\PaymentMethod;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Modules\Sales\Domain\SaleItem;
use App\Modules\Sales\Domain\SaleRepositoryInterface;
use App\Modules\Sales\Domain\SaleStatus;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SaleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_reads_lists_and_refunds_sale(): void
    {
        $staff = User::factory()->create();
        $companyId = CompanyModel::create(['owner_id' => $staff->id, 'name' => 'Gym Co'])->id;

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientId = $clients->create($companyId, 'John Doe', '+992900111222');

        /** @var ProductRepositoryInterface $products */
        $products = $this->app->make(ProductRepositoryInterface::class);
        $productId = $products->create($companyId, 'Water', new Money(500, 'TJS'), 10);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $typeId = $types->create(
            $companyId,
            'Monthly',
            new Money(20000, 'TJS'),
            MembershipRules::create(MembershipKind::Duration, StartMode::OnSale, durationDays: 30)
        );

        /** @var MembershipIssuerInterface $issuer */
        $issuer = $this->app->make(MembershipIssuerInterface::class);
        $paidAt = new DateTimeImmutable('2026-10-28 10:00:00');
        $membershipId = $issuer->issue($companyId, $clientId, $typeId, $paidAt, $staff->id);

        /** @var SaleRepositoryInterface $sales */
        $sales = $this->app->make(SaleRepositoryInterface::class);

        $sale = $sales->create(
            companyId: $companyId,
            clientId: $clientId,
            paymentMark: new PaymentMark(
                method: PaymentMethod::Cash,
                amount: new Money(21000, 'TJS'),
                acceptedByUserId: $staff->id,
                paidAt: $paidAt
            ),
            items: [
                SaleItem::forMembership($typeId, 'Monthly', new Money(20000, 'TJS'), $membershipId),
                SaleItem::forProduct($productId, 'Water', new Money(500, 'TJS'), 2),
            ]
        );

        $loaded = $sales->findById($sale->id());
        $this->assertNotNull($loaded);
        $this->assertSame($clientId, $loaded->clientId());
        $this->assertSame(21000, $loaded->paymentMark()->amount->amount());
        $this->assertCount(2, $loaded->items());
        $this->assertCount(1, $sales->listByCompany($companyId));
        $this->assertCount(1, $sales->listByClient($companyId, $clientId));

        $loaded->refund('Ошибка кассира', $staff->id, new DateTimeImmutable('2026-10-28 11:00:00'));
        $sales->save($loaded);

        $reloaded = $sales->findById($sale->id());
        $this->assertNotNull($reloaded);
        $this->assertSame(SaleStatus::Refunded, $reloaded->status());
        $this->assertSame('Ошибка кассира', $reloaded->refundReason());
        $this->assertSame($staff->id, $reloaded->refundedByUserId());
    }
}

