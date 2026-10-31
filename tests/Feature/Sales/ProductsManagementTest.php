<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductsManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createOwnerWithCompany(): User
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'sanctum')->postJson('/api/v1/business/companies', [
            'company_name' => 'Test Co',
            'gym_name' => 'Main Gym',
        ])->assertStatus(201);

        return $owner;
    }

    private function companyIdFor(User $user): int
    {
        return (int) StaffMemberModel::query()->where('user_id', $user->id)->value('company_id');
    }

    public function test_owner_creates_and_updates_product_and_staff_can_list(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);

        $res = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/business/products', [
                'name' => 'Water 0.5L',
                'price_minor' => 500,
                'stock_quantity' => 30,
            ])
            ->assertStatus(201);

        $id = (int) $res->json('data.id');

        $admin = User::factory()->create();
        StaffMemberModel::create([
            'user_id' => $admin->id,
            'company_id' => $companyId,
            'role' => Role::ADMIN->value,
        ]);

        // Admin can list products
        $list = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/business/products')
            ->assertOk();

        $this->assertCount(1, $list->json('data'));
        $this->assertSame('Water 0.5L', $list->json('data.0.name'));
        $this->assertSame(30, $list->json('data.0.stock_quantity'));
        $this->assertSame('TJS', $list->json('data.0.currency'));

        // Owner updates stock and deactivates product
        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/business/products/{$id}", [
                'stock_quantity' => 50,
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.stock_quantity', 50)
            ->assertJsonPath('data.is_active', false);

        // Filter only_active=1 returns 0
        $activeList = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/business/products?only_active=1')
            ->assertOk();

        $this->assertCount(0, $activeList->json('data'));
    }

    public function test_admin_cannot_create_or_update_products(): void
    {
        $owner = $this->createOwnerWithCompany();
        $companyId = $this->companyIdFor($owner);

        $res = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/business/products', [
                'name' => 'Towel Rent',
                'price_minor' => 1000,
            ])
            ->assertStatus(201);
        $id = (int) $res->json('data.id');

        $admin = User::factory()->create();
        StaffMemberModel::create([
            'user_id' => $admin->id,
            'company_id' => $companyId,
            'role' => Role::ADMIN->value,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/business/products', [
                'name' => 'Admin Product',
                'price_minor' => 500,
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/business/products/{$id}", ['is_active' => false])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_cannot_update_or_see_products_of_another_company(): void
    {
        $owner1 = $this->createOwnerWithCompany();
        $res = $this->actingAs($owner1, 'sanctum')
            ->postJson('/api/v1/business/products', [
                'name' => 'Secret Shake',
                'price_minor' => 2500,
            ])
            ->assertStatus(201);
        $productId = (int) $res->json('data.id');

        $owner2 = $this->createOwnerWithCompany();

        $list = $this->actingAs($owner2, 'sanctum')
            ->getJson('/api/v1/business/products')
            ->assertOk();
        $this->assertCount(0, $list->json('data'));

        $this->actingAs($owner2, 'sanctum')
            ->patchJson("/api/v1/business/products/{$productId}", ['is_active' => false])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'PRODUCT_NOT_FOUND');
    }

    public function test_product_creation_defaults_to_company_currency(): void
    {
        $owner = $this->createOwnerWithCompany();

        // Меняем валюту компании на USD (id 3)
        $this->actingAs($owner, 'sanctum')->patchJson('/api/v1/business/company/currency', [
            'currency_id' => 3,
        ])->assertStatus(200);

        $res = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/business/products', [
                'name' => 'Protein Bar USD',
                'price_minor' => 250,
            ])
            ->assertStatus(201);

        $productId = (int) $res->json('data.id');

        $this->assertDatabaseHas('sales_products', [
            'id' => $productId,
            'name' => 'Protein Bar USD',
            'currency' => 'USD',
        ]);
    }
}
