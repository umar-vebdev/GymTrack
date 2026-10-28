<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Sales\Application\Commands\RegisterSaleCommand;
use App\Modules\Sales\Application\RegisterSale;
use App\Modules\Sales\Domain\PaymentMethod;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Shared\Domain\Money;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegisterSaleTest extends TestCase
{
    use RefreshDatabase;

    private int $staffId;

    private function createCompany(string $name = 'Gym Co'): int
    {
        $user = User::factory()->create();
        $this->staffId = $user->id;

        return CompanyModel::create(['owner_id' => $user->id, 'name' => $name])->id;
    }

    private function createClient(int $companyId, string $phone = '+992900111222'): int
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);

        return $clients->create($companyId, 'John Doe', $phone);
    }

    private function createProduct(int $companyId, string $name, int $priceMinor, ?int $stock): int
    {
        /** @var ProductRepositoryInterface $products */
        $products = $this->app->make(ProductRepositoryInterface::class);

        return $products->create($companyId, $name, new Money($priceMinor, 'TJS'), $stock);
    }

    private function createMembershipType(int $companyId, string $name, int $priceMinor): int
    {
        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);

        return $types->create(
            $companyId,
            $name,
            new Money($priceMinor, 'TJS'),
            MembershipRules::create(MembershipKind::Visits, StartMode::OnSale, visitsCount: 12, validityDays: 60)
        );
    }

    private function useCase(): RegisterSale
    {
        return $this->app->make(RegisterSale::class);
    }

    public function test_registers_sale_of_membership_and_products_in_one_transaction_and_writes_audit(): void
    {
        $companyId = $this->createCompany();
        $clientId = $this->createClient($companyId);
        $productId = $this->createProduct($companyId, 'Water 0.5L', 500, 10);
        $typeId = $this->createMembershipType($companyId, '12 Visits', 20000);

        $sale = $this->useCase()->execute(new RegisterSaleCommand(
            companyId: $companyId,
            acceptedByUserId: $this->staffId,
            clientId: $clientId,
            paymentMethod: PaymentMethod::Cash,
            products: [
                ['product_id' => $productId, 'quantity' => 2],
            ],
            membershipTypeIds: [$typeId]
        ));

        $this->assertSame(21000, $sale->paymentMark()->amount->amount());
        $this->assertCount(2, $sale->items());

        // Stock decremented from 10 to 8
        $this->assertDatabaseHas('sales_products', [
            'id' => $productId,
            'stock_quantity' => 8,
        ]);

        // Membership issued in DB and linked to sale item
        $issuedId = $sale->items()[1]->issuedMembershipId;
        $this->assertNotNull($issuedId);
        $this->assertDatabaseHas('memberships', [
            'id' => $issuedId,
            'company_id' => $companyId,
            'client_id' => $clientId,
            'type_id' => $typeId,
            'status' => 'active',
            'remaining_visits' => 12,
        ]);

        // Both MEMBERSHIP_ISSUED and PAYMENT_MARKED recorded in audit_entries
        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $companyId,
            'user_id' => $this->staffId,
            'action' => 'MEMBERSHIP_ISSUED',
            'entity_id' => (string) $issuedId,
        ]);
        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $companyId,
            'user_id' => $this->staffId,
            'action' => 'PAYMENT_MARKED',
            'entity_type' => 'Sale',
            'entity_id' => (string) $sale->id(),
        ]);
    }

    public function test_rolls_back_entire_sale_if_membership_issuance_or_stock_fails(): void
    {
        $companyId = $this->createCompany();
        $clientId = $this->createClient($companyId);
        $productId = $this->createProduct($companyId, 'Water 0.5L', 500, 10);

        try {
            $this->useCase()->execute(new RegisterSaleCommand(
                companyId: $companyId,
                acceptedByUserId: $this->staffId,
                clientId: $clientId,
                paymentMethod: PaymentMethod::CardTerminal,
                products: [
                    ['product_id' => $productId, 'quantity' => 3],
                ],
                membershipTypeIds: [999999] // non-existing membership type -> throws inside transaction
            ));
            $this->fail('Expected DomainException for non-existing membership type.');
        } catch (DomainException) {
            // expected
        }

        // Stock must remain 10 (transaction rolled back!)
        $this->assertDatabaseHas('sales_products', [
            'id' => $productId,
            'stock_quantity' => 10,
        ]);
        $this->assertDatabaseCount('sales_sales', 0);
        $this->assertDatabaseCount('memberships', 0);
    }
}
