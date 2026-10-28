<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure;

use App\Modules\Sales\Domain\Product;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Modules\Sales\Infrastructure\Persistence\ProductModel;
use App\Shared\Domain\Money;

final class EloquentProductRepository implements ProductRepositoryInterface
{
    public function findById(int $id): ?Product
    {
        $model = ProductModel::withoutGlobalScopes()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByIdForUpdate(int $id): ?Product
    {
        $model = ProductModel::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function create(
        int $companyId,
        string $name,
        Money $price,
        ?int $stockQuantity
    ): int {
        $model = ProductModel::create([
            'company_id' => $companyId,
            'name' => $name,
            'price_minor' => $price->amount(),
            'currency' => $price->currency(),
            'stock_quantity' => $stockQuantity,
            'is_active' => true,
        ]);

        return $model->id;
    }

    public function listByCompany(int $companyId, ?bool $onlyActive = null): array
    {
        $query = ProductModel::withoutGlobalScopes()
            ->where('company_id', $companyId);

        if ($onlyActive !== null) {
            $query->where('is_active', $onlyActive);
        }

        $models = $query->orderBy('name')->orderBy('id')->get();

        return array_values($models->map(fn (ProductModel $m): Product => $this->toEntity($m))->all());
    }

    public function save(Product $product): void
    {
        ProductModel::withoutGlobalScopes()
            ->whereKey($product->id())
            ->update([
                'stock_quantity' => $product->stockQuantity(),
                'is_active' => $product->isActive(),
            ]);
    }

    private function toEntity(ProductModel $m): Product
    {
        return new Product(
            id: $m->id,
            companyId: $m->company_id,
            name: $m->name,
            price: new Money($m->price_minor, $m->currency),
            stockQuantity: $m->stock_quantity,
            isActive: $m->is_active
        );
    }
}
