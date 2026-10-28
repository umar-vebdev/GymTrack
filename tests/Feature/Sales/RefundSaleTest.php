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

final class RefundSaleTest extends TestCase
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

    public function test_owner_refunds_sale_restores_stock_cancels_issued_membership_and_writes_audit(): void
    {
        ['owner' => $owner, 'admin' => $admin, 'companyId' => $companyId] = $this->createCompanyWithStaff();
        $clientId = $this->createClient($companyId);
        $waterId = $this->createProduct($companyId, 'Water 0.5L', 500, 10);
        $typeId = $this->createMembershipType($companyId, '12 Visits', 20000);

        Sanctum::actingAs($admin);

        $saleRes = $this->postJson('/api/v1/business/sales', [
            'client_id' => $clientId,
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $waterId, 'quantity' => 3],
            ],
            'membership_type_ids' => [$typeId],
        ])->assertCreated();

        $saleId = (int) $saleRes->json('data.id');
        $issuedMembershipId = (int) $saleRes->json('data.items.1.issued_membership_id');

        $this->assertDatabaseHas('sales_products', [
            'id' => $waterId,
            'stock_quantity' => 7,
        ]);

        // Admin cannot refund
        $this->postJson("/api/v1/business/sales/{$saleId}/refund", [
            'reason' => 'Ошибочный чек',
        ])->assertForbidden();

        // Owner requires non-empty reason
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/business/sales/{$saleId}/refund", [
            'reason' => '   ',
        ])->assertStatus(422);

        // Owner refunds sale
        $refundRes = $this->postJson("/api/v1/business/sales/{$saleId}/refund", [
            'reason' => 'Ошибочный чек',
        ]);

        $refundRes->assertOk()
            ->assertJsonPath('data.id', $saleId)
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.refund_reason', 'Ошибочный чек')
            ->assertJsonPath('data.refunded_by_user_id', $owner->id);

        // Product stock restored from 7 back to 10
        $this->assertDatabaseHas('sales_products', [
            'id' => $waterId,
            'stock_quantity' => 10,
        ]);

        // Issued membership cancelled
        $this->assertDatabaseHas('memberships', [
            'id' => $issuedMembershipId,
            'status' => 'cancelled',
            'cancellation_reason' => 'Ошибочный чек',
        ]);

        // Both MEMBERSHIP_CANCELLED and SALE_REFUNDED recorded in audit_entries
        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'action' => 'MEMBERSHIP_CANCELLED',
            'entity_id' => (string) $issuedMembershipId,
        ]);
        $this->assertDatabaseHas('audit_entries', [
            'company_id' => $companyId,
            'user_id' => $owner->id,
            'action' => 'SALE_REFUNDED',
            'entity_type' => 'Sale',
            'entity_id' => (string) $saleId,
        ]);

        // Cannot refund twice
        $this->postJson("/api/v1/business/sales/{$saleId}/refund", [
            'reason' => 'Повторный возврат',
        ])->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');
    }

    public function test_cannot_refund_sale_of_another_company(): void
    {
        ['owner' => $ownerA] = $this->createCompanyWithStaff('Gym A');
        ['owner' => $ownerB, 'companyId' => $companyB] = $this->createCompanyWithStaff('Gym B');
        $waterB = $this->createProduct($companyB, 'Water B', 500, 10);

        Sanctum::actingAs($ownerB);
        $saleRes = $this->postJson('/api/v1/business/sales', [
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $waterB, 'quantity' => 1],
            ],
        ])->assertCreated();

        $saleId = (int) $saleRes->json('data.id');

        Sanctum::actingAs($ownerA);
        $this->postJson("/api/v1/business/sales/{$saleId}/refund", [
            'reason' => 'Чужой чек',
        ])->assertNotFound()
            ->assertJsonPath('error.code', 'SALE_NOT_FOUND');
    }
}
