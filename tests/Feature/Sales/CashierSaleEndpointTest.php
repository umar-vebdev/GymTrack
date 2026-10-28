<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Shared\Domain\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class CashierSaleEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, operator: User, companyId: int}
     */
    private function createCompanyWithStaff(string $name = 'Gym Co'): array
    {
        $owner = User::factory()->create();
        $operator = User::factory()->create();

        $company = CompanyModel::create([
            'owner_id' => $owner->id,
            'name' => $name,
        ]);

        StaffMemberModel::create([
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => Role::OWNER->value,
        ]);

        StaffMemberModel::create([
            'company_id' => $company->id,
            'user_id' => $operator->id,
            'role' => Role::ADMIN->value,
        ]);

        return ['owner' => $owner, 'operator' => $operator, 'companyId' => $company->id];
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

    public function test_operator_can_sell_products_without_client(): void
    {
        ['operator' => $operator, 'companyId' => $companyId] = $this->createCompanyWithStaff();
        $waterId = $this->createProduct($companyId, 'Water 0.5L', 500, 10);

        Sanctum::actingAs($operator);

        $response = $this->postJson('/api/v1/business/sales', [
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $waterId, 'quantity' => 3],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.company_id', $companyId)
            ->assertJsonPath('data.client_id', null)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.total_minor', 1500)
            ->assertJsonPath('data.currency', 'TJS')
            ->assertJsonPath('data.accepted_by_user_id', $operator->id)
            ->assertJsonCount(1, 'data.items');

        $this->assertDatabaseHas('sales_products', [
            'id' => $waterId,
            'stock_quantity' => 7,
        ]);
    }

    public function test_idempotency_key_prevents_double_sale_double_stock_deduction_and_double_membership(): void
    {
        ['operator' => $operator, 'companyId' => $companyId] = $this->createCompanyWithStaff();
        $clientId = $this->createClient($companyId);
        $waterId = $this->createProduct($companyId, 'Water 0.5L', 500, 10);
        $typeId = $this->createMembershipType($companyId, '12 Visits', 20000);

        Sanctum::actingAs($operator);

        $payload = [
            'client_id' => $clientId,
            'payment_method' => 'card_terminal',
            'products' => [
                ['product_id' => $waterId, 'quantity' => 2],
            ],
            'membership_type_ids' => [$typeId],
        ];

        $headers = ['Idempotency-Key' => 'cashier-req-001'];

        $first = $this->postJson('/api/v1/business/sales', $payload, $headers);
        $first->assertCreated()
            ->assertJsonPath('data.total_minor', 21000);

        $saleId = $first->json('data.id');
        $issuedMembershipId = $first->json('data.items.1.issued_membership_id');
        $this->assertNotNull($issuedMembershipId);

        // Repeat exact same request with the same Idempotency-Key
        $second = $this->postJson('/api/v1/business/sales', $payload, $headers);
        $second->assertCreated()
            ->assertJsonPath('data.id', $saleId)
            ->assertJsonPath('data.items.1.issued_membership_id', $issuedMembershipId);

        // Stock decremented only once (10 -> 8)
        $this->assertDatabaseHas('sales_products', [
            'id' => $waterId,
            'stock_quantity' => 8,
        ]);

        // Only 1 sale, 1 membership, and 1 PAYMENT_MARKED audit entry created
        $this->assertDatabaseCount('sales_sales', 1);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertSame(
            1,
            DB::table('audit_entries')->where('action', 'PAYMENT_MARKED')->count()
        );
    }

    public function test_rejects_sale_when_stock_is_insufficient(): void
    {
        ['operator' => $operator, 'companyId' => $companyId] = $this->createCompanyWithStaff();
        $barId = $this->createProduct($companyId, 'Protein Bar', 1500, 2);

        Sanctum::actingAs($operator);

        $response = $this->postJson('/api/v1/business/sales', [
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $barId, 'quantity' => 5],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');

        $this->assertDatabaseHas('sales_products', [
            'id' => $barId,
            'stock_quantity' => 2,
        ]);
        $this->assertDatabaseCount('sales_sales', 0);
    }

    public function test_isolates_companies_on_cashier_sale(): void
    {
        ['operator' => $operatorA] = $this->createCompanyWithStaff('Gym A');
        ['companyId' => $companyB] = $this->createCompanyWithStaff('Gym B');
        $foreignProductId = $this->createProduct($companyB, 'Foreign Water', 500, 10);

        Sanctum::actingAs($operatorA);

        $response = $this->postJson('/api/v1/business/sales', [
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $foreignProductId, 'quantity' => 1],
            ],
        ]);

        $response->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
