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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ListSalesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{owner: User, admin: User, companyId: int}
     */
    private function createCompanyWithStaff(string $name = 'Gym Co'): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

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
            'user_id' => $admin->id,
            'role' => Role::ADMIN->value,
        ]);

        return ['owner' => $owner, 'admin' => $admin, 'companyId' => $company->id];
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

    public function test_staff_can_list_company_sales_and_client_purchase_history(): void
    {
        ['owner' => $owner, 'admin' => $admin, 'companyId' => $companyId] = $this->createCompanyWithStaff();
        $clientId = $this->createClient($companyId);
        $waterId = $this->createProduct($companyId, 'Water 0.5L', 500, 10);
        $typeId = $this->createMembershipType($companyId, '12 Visits', 20000);

        Sanctum::actingAs($owner);

        // Sale 1: without client
        $this->postJson('/api/v1/business/sales', [
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $waterId, 'quantity' => 1],
            ],
        ])->assertCreated();

        // Sale 2: for client (membership + water)
        $this->postJson('/api/v1/business/sales', [
            'client_id' => $clientId,
            'payment_method' => 'card_terminal',
            'products' => [
                ['product_id' => $waterId, 'quantity' => 2],
            ],
            'membership_type_ids' => [$typeId],
        ])->assertCreated();

        Sanctum::actingAs($admin);

        $companySales = $this->getJson('/api/v1/business/sales')->assertOk();
        $this->assertCount(2, $companySales->json('data'));

        $clientSales = $this->getJson("/api/v1/business/clients/{$clientId}/sales")->assertOk();
        $this->assertCount(1, $clientSales->json('data'));
        $clientSales->assertJsonPath('data.0.client_id', $clientId)
            ->assertJsonPath('data.0.total_minor', 21000)
            ->assertJsonCount(2, 'data.0.items');
    }

    public function test_isolates_company_sales_and_forbids_foreign_client_sales(): void
    {
        ['admin' => $adminA, 'companyId' => $companyA] = $this->createCompanyWithStaff('Gym A');
        ['owner' => $ownerB, 'companyId' => $companyB] = $this->createCompanyWithStaff('Gym B');

        $clientB = $this->createClient($companyB, '+992900999888');
        $waterB = $this->createProduct($companyB, 'Water B', 500, 10);

        Sanctum::actingAs($ownerB);
        $this->postJson('/api/v1/business/sales', [
            'client_id' => $clientB,
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $waterB, 'quantity' => 1],
            ],
        ])->assertCreated();

        Sanctum::actingAs($adminA);

        $this->getJson('/api/v1/business/sales')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/business/clients/{$clientB}/sales")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CLIENT_NOT_FOUND');

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);

        $this->getJson('/api/v1/business/sales')->assertForbidden();
        $this->getJson("/api/v1/business/clients/{$clientB}/sales")->assertForbidden();
    }
}

