<?php

declare(strict_types=1);

namespace Tests\Unit\Sales;

use App\Models\User;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Shared\Domain\Money;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name = 'Gym Co'): int
    {
        $user = User::factory()->create();

        return CompanyModel::create(['owner_id' => $user->id, 'name' => $name])->id;
    }

    private function repository(): ProductRepositoryInterface
    {
        return $this->app->make(ProductRepositoryInterface::class);
    }

    public function test_it_creates_reads_and_updates_product_stock_and_active_flag(): void
    {
        $companyId = $this->createCompany();
        $repo = $this->repository();

        $id = $repo->create($companyId, 'Water 0.5L', new Money(500, 'TJS'), 20);

        $product = $repo->findById($id);
        $this->assertNotNull($product);
        $this->assertSame('Water 0.5L', $product->name());
        $this->assertSame(500, $product->price()->amount());
        $this->assertSame(20, $product->stockQuantity());
        $this->assertTrue($product->isActive());

        DB::transaction(function () use ($repo, $id): void {
            $locked = $repo->findByIdForUpdate($id);
            $this->assertNotNull($locked);
            $locked->decrementStock(3);
            $locked->setActive(false);
            $repo->save($locked);
        });

        $reloaded = $repo->findById($id);
        $this->assertNotNull($reloaded);
        $this->assertSame(17, $reloaded->stockQuantity());
        $this->assertFalse($reloaded->isActive());
        $this->assertCount(0, $repo->listByCompany($companyId, onlyActive: true));
        $this->assertCount(1, $repo->listByCompany($companyId));
    }

    public function test_domain_and_database_reject_negative_stock(): void
    {
        $companyId = $this->createCompany();
        $repo = $this->repository();

        $id = $repo->create($companyId, 'Protein Bar', new Money(1500, 'TJS'), 2);
        $product = $repo->findById($id);
        $this->assertNotNull($product);

        try {
            $product->decrementStock(5);
            $this->fail('Expected DomainException when decrementing beyond stock.');
        } catch (DomainException) {
            // expected
        }

        $this->expectException(QueryException::class);
        DB::table('sales_products')->where('id', $id)->update(['stock_quantity' => -1]);
    }
}
