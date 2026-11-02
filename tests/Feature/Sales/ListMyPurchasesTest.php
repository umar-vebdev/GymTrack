<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Sales\Domain\PaymentMark;
use App\Modules\Sales\Domain\PaymentMethod;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Modules\Sales\Domain\SaleItem;
use App\Modules\Sales\Domain\SaleRepositoryInterface;
use App\Shared\Domain\Money;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ListMyPurchasesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{companyId: int, adminId: int}
     */
    private function createCompany(string $name): array
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $company = CompanyModel::create([
            'owner_id' => $owner->id,
            'name' => $name,
        ]);

        return [
            'companyId' => $company->id,
            'adminId' => $admin->id,
        ];
    }

    private function createClient(int $companyId, int $userId): int
    {
        /** @var ClientRepositoryInterface $clients */
        $clients = $this->app->make(ClientRepositoryInterface::class);

        return $clients->create($companyId, 'Test Client', '+992900111222', $userId);
    }

    private function createProduct(int $companyId, string $name, int $priceMinor): int
    {
        /** @var ProductRepositoryInterface $products */
        $products = $this->app->make(ProductRepositoryInterface::class);

        return $products->create($companyId, $name, new Money($priceMinor, 'TJS'), 100);
    }

    public function test_unauthenticated_client_cannot_access_my_purchases(): void
    {
        $response = $this->getJson('/api/v1/client/purchases');

        $response->assertStatus(401);
    }

    public function test_client_without_cards_gets_empty_list_with_meta(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/client/purchases');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [],
            'meta' => [
                'page' => 1,
                'per_page' => 20,
                'total' => 0,
            ],
        ]);
    }

    public function test_client_gets_all_purchases_across_different_companies(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $companyA = $this->createCompany('Gym Alpha');
        $companyB = $this->createCompany('Gym Beta');

        $clientA = $this->createClient($companyA['companyId'], $user->id);
        $clientB = $this->createClient($companyB['companyId'], $user->id);

        $prodA = $this->createProduct($companyA['companyId'], 'Energy Drink', 1500);
        $prodB = $this->createProduct($companyB['companyId'], 'Protein Bar', 2000);

        /** @var SaleRepositoryInterface $sales */
        $sales = $this->app->make(SaleRepositoryInterface::class);

        $sales->create(
            companyId: $companyA['companyId'],
            clientId: $clientA,
            paymentMark: new PaymentMark(
                method: PaymentMethod::Cash,
                amount: new Money(1500, 'TJS'),
                acceptedByUserId: $companyA['adminId'],
                paidAt: new DateTimeImmutable('2026-11-01 10:00:00')
            ),
            items: [
                SaleItem::forProduct($prodA, 'Energy Drink', new Money(1500, 'TJS'), 1),
            ]
        );

        $sales->create(
            companyId: $companyB['companyId'],
            clientId: $clientB,
            paymentMark: new PaymentMark(
                method: PaymentMethod::CardTerminal,
                amount: new Money(2000, 'TJS'),
                acceptedByUserId: $companyB['adminId'],
                paidAt: new DateTimeImmutable('2026-11-02 11:00:00')
            ),
            items: [
                SaleItem::forProduct($prodB, 'Protein Bar', new Money(2000, 'TJS'), 1),
            ]
        );

        $response = $this->getJson('/api/v1/client/purchases');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 2);
        // Latest first
        $response->assertJsonPath('data.0.company_id', $companyB['companyId']);
        $response->assertJsonPath('data.0.total_minor', 2000);
        $response->assertJsonPath('data.0.payment_method', 'card_terminal');
        $response->assertJsonPath('data.0.items.0.name', 'Protein Bar');

        $response->assertJsonPath('data.1.company_id', $companyA['companyId']);
        $response->assertJsonPath('data.1.total_minor', 1500);
        $response->assertJsonPath('data.1.payment_method', 'cash');
        $response->assertJsonPath('data.1.items.0.name', 'Energy Drink');
    }

    public function test_client_can_filter_purchases_by_company(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $companyA = $this->createCompany('Gym Alpha');
        $companyB = $this->createCompany('Gym Beta');

        $clientA = $this->createClient($companyA['companyId'], $user->id);
        $clientB = $this->createClient($companyB['companyId'], $user->id);

        $prodA = $this->createProduct($companyA['companyId'], 'Water', 500);
        $prodB = $this->createProduct($companyB['companyId'], 'Towel', 3000);

        /** @var SaleRepositoryInterface $sales */
        $sales = $this->app->make(SaleRepositoryInterface::class);

        $sales->create(
            companyId: $companyA['companyId'],
            clientId: $clientA,
            paymentMark: new PaymentMark(
                method: PaymentMethod::Cash,
                amount: new Money(500, 'TJS'),
                acceptedByUserId: $companyA['adminId'],
                paidAt: new DateTimeImmutable('2026-11-01 10:00:00')
            ),
            items: [
                SaleItem::forProduct($prodA, 'Water', new Money(500, 'TJS'), 1),
            ]
        );

        $sales->create(
            companyId: $companyB['companyId'],
            clientId: $clientB,
            paymentMark: new PaymentMark(
                method: PaymentMethod::CardTerminal,
                amount: new Money(3000, 'TJS'),
                acceptedByUserId: $companyB['adminId'],
                paidAt: new DateTimeImmutable('2026-11-02 11:00:00')
            ),
            items: [
                SaleItem::forProduct($prodB, 'Towel', new Money(3000, 'TJS'), 1),
            ]
        );

        $response = $this->getJson('/api/v1/client/purchases?company_id='.$companyA['companyId']);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('data.0.company_id', $companyA['companyId']);
        $response->assertJsonPath('data.0.items.0.name', 'Water');
    }

    public function test_client_can_filter_purchases_by_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->createCompany('Gym Alpha');
        $client = $this->createClient($company['companyId'], $user->id);
        $prod = $this->createProduct($company['companyId'], 'Water', 500);

        /** @var SaleRepositoryInterface $sales */
        $sales = $this->app->make(SaleRepositoryInterface::class);

        $sale1 = $sales->create(
            companyId: $company['companyId'],
            clientId: $client,
            paymentMark: new PaymentMark(
                method: PaymentMethod::Cash,
                amount: new Money(500, 'TJS'),
                acceptedByUserId: $company['adminId'],
                paidAt: new DateTimeImmutable('2026-11-01 10:00:00')
            ),
            items: [
                SaleItem::forProduct($prod, 'Water', new Money(500, 'TJS'), 1),
            ]
        );

        $sale2 = $sales->create(
            companyId: $company['companyId'],
            clientId: $client,
            paymentMark: new PaymentMark(
                method: PaymentMethod::Cash,
                amount: new Money(500, 'TJS'),
                acceptedByUserId: $company['adminId'],
                paidAt: new DateTimeImmutable('2026-11-02 10:00:00')
            ),
            items: [
                SaleItem::forProduct($prod, 'Water', new Money(500, 'TJS'), 1),
            ]
        );

        // Refund sale2
        $sale2->refund('Customer requested', $company['adminId'], new DateTimeImmutable('2026-11-02 11:00:00'));
        $sales->save($sale2);

        $responseCompleted = $this->getJson('/api/v1/client/purchases?status=completed');
        $responseCompleted->assertStatus(200);
        $responseCompleted->assertJsonCount(1, 'data');
        $responseCompleted->assertJsonPath('data.0.id', $sale1->id());
        $responseCompleted->assertJsonPath('data.0.status', 'completed');

        $responseRefunded = $this->getJson('/api/v1/client/purchases?status=refunded');
        $responseRefunded->assertStatus(200);
        $responseRefunded->assertJsonCount(1, 'data');
        $responseRefunded->assertJsonPath('data.0.id', $sale2->id());
        $responseRefunded->assertJsonPath('data.0.status', 'refunded');
        $responseRefunded->assertJsonPath('data.0.refund_reason', 'Customer requested');
    }

    public function test_client_can_paginate_purchases(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $company = $this->createCompany('Gym Alpha');
        $client = $this->createClient($company['companyId'], $user->id);
        $prod = $this->createProduct($company['companyId'], 'Water', 500);

        /** @var SaleRepositoryInterface $sales */
        $sales = $this->app->make(SaleRepositoryInterface::class);

        for ($i = 1; $i <= 5; $i++) {
            $sales->create(
                companyId: $company['companyId'],
                clientId: $client,
                paymentMark: new PaymentMark(
                    method: PaymentMethod::Cash,
                    amount: new Money(500, 'TJS'),
                    acceptedByUserId: $company['adminId'],
                    paidAt: new DateTimeImmutable("2026-11-0{$i} 10:00:00")
                ),
                items: [
                    SaleItem::forProduct($prod, 'Water', new Money(500, 'TJS'), 1),
                ]
            );
        }

        $response = $this->getJson('/api/v1/client/purchases?page=2&per_page=2');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('meta.total', 5);
        $response->assertJsonPath('meta.page', 2);
        $response->assertJsonPath('meta.per_page', 2);
    }

    public function test_invalid_query_parameters_return_validation_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $responseStatus = $this->getJson('/api/v1/client/purchases?status=unknown');
        $responseStatus->assertStatus(422);
        $responseStatus->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertArrayHasKey('status', $responseStatus->json('error.details'));

        $responsePerPage = $this->getJson('/api/v1/client/purchases?per_page=200');
        $responsePerPage->assertStatus(422);
        $responsePerPage->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertArrayHasKey('per_page', $responsePerPage->json('error.details'));
    }
}
