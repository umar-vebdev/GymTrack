<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

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

final class AnalyticsEndpointsTest extends TestCase
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

    public function test_owner_gets_revenue_and_overview_reports_and_isolates_tenants(): void
    {
        ['owner' => $ownerA, 'admin' => $adminA, 'companyId' => $companyA] = $this->createCompanyWithStaff('Gym A');
        ['owner' => $ownerB, 'companyId' => $companyB] = $this->createCompanyWithStaff('Gym B');

        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);
        $clientA = $clients->create($companyA, 'Client A1', '+992900111222');
        $clients->create($companyA, 'Client A2', '+992900111333');
        $clients->create($companyB, 'Client B1', '+992900999999');

        /** @var ProductRepositoryInterface $products */
        $products = $this->app->make(ProductRepositoryInterface::class);
        $waterId = $products->create($companyA, 'Water 0.5L', new Money(500, 'TJS'), 20);
        $barId = $products->create($companyA, 'Protein Bar', new Money(1500, 'TJS'), 20);
        $otherProduct = $products->create($companyB, 'Towel', new Money(5000, 'TJS'), 20);

        /** @var MembershipTypeRepositoryInterface $types */
        $types = $this->app->make(MembershipTypeRepositoryInterface::class);
        $monthlyId = $types->create(
            $companyA,
            'Monthly Unlimited',
            new Money(20000, 'TJS'),
            MembershipRules::create(MembershipKind::Unlimited, StartMode::OnSale, durationDays: 30)
        );

        // Администратор проводит продажи на кассе компании A
        Sanctum::actingAs($adminA);

        $this->postJson('/api/v1/business/sales', [
            'client_id' => $clientA,
            'payment_method' => 'cash',
            'membership_type_ids' => [$monthlyId],
            'products' => [
                ['product_id' => $waterId, 'quantity' => 2],
            ],
        ])->assertCreated();

        $this->postJson('/api/v1/business/sales', [
            'payment_method' => 'card_terminal',
            'products' => [
                ['product_id' => $barId, 'quantity' => 3],
            ],
        ])->assertCreated();

        // Продажа в компании B (не должна попасть в отчёт компании A — критерий приёмки 12.6)
        Sanctum::actingAs($ownerB);
        $this->postJson('/api/v1/business/sales', [
            'payment_method' => 'cash',
            'products' => [
                ['product_id' => $otherProduct, 'quantity' => 2],
            ],
        ])->assertCreated();

        // Владелец компании A запрашивает финансовый отчёт и сводку
        Sanctum::actingAs($ownerA);

        $revenueRes = $this->getJson('/api/v1/business/analytics/revenue');
        $revenueRes->assertOk()
            ->assertJsonPath('data.total_minor', 25500)
            ->assertJsonPath('data.memberships_minor', 20000)
            ->assertJsonPath('data.products_minor', 5500)
            ->assertJsonPath('data.sales_count', 2)
            ->assertJsonPath('data.by_payment_method.cash', 21000)
            ->assertJsonPath('data.by_payment_method.card_terminal', 4500)
            ->assertJsonPath('data.by_payment_method.transfer', 0);

        $overviewRes = $this->getJson('/api/v1/business/analytics/overview');
        $overviewRes->assertOk()
            ->assertJsonPath('data.revenue.total_minor', 25500)
            ->assertJsonPath('data.clients.total_clients', 2)
            ->assertJsonPath('data.clients.new_clients', 2)
            ->assertJsonPath('data.clients.existing_clients', 0)
            ->assertJsonPath('data.visits.total_visits', 0)
            ->assertJsonPath('data.visits.inactive_clients_count', 2)
            ->assertJsonPath('data.popular_memberships.0.item_id', $monthlyId)
            ->assertJsonPath('data.popular_memberships.0.sold_count', 1)
            ->assertJsonPath('data.popular_products.0.item_id', $barId)
            ->assertJsonPath('data.popular_products.0.sold_count', 3)
            ->assertJsonPath('data.popular_products.1.item_id', $waterId)
            ->assertJsonPath('data.popular_products.1.sold_count', 2);
    }

    public function test_admin_and_stranger_cannot_access_financial_analytics(): void
    {
        ['admin' => $admin] = $this->createCompanyWithStaff();
        $stranger = User::factory()->create();

        // Критерий приёмки 12.7: роль «Администратор» получает 403 FORBIDDEN при открытии финансовой аналитики
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/business/analytics/revenue')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->getJson('/api/v1/business/analytics/overview')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        // Посторонний пользователь также получает 403 FORBIDDEN
        Sanctum::actingAs($stranger);

        $this->getJson('/api/v1/business/analytics/revenue')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_invalid_period_range_returns_422(): void
    {
        ['owner' => $owner] = $this->createCompanyWithStaff();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/business/analytics/revenue?from=2026-10-20&to=2026-10-10')
            ->assertStatus(422);
    }
}
