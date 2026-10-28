<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Domain\Exceptions\ProductNotFound;
use App\Modules\Sales\Domain\Product;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class UpdateProduct
{
    public function __construct(
        private readonly ProductRepositoryInterface $products
    ) {}

    /**
     * @throws ProductNotFound
     */
    public function execute(
        int $companyId,
        int $productId,
        ?bool $isActive = null,
        bool $updateStock = false,
        ?int $stockQuantity = null
    ): Product {
        return DB::transaction(function () use ($companyId, $productId, $isActive, $updateStock, $stockQuantity): Product {
            $product = $this->products->findByIdForUpdate($productId);

            if ($product === null || $product->companyId() !== $companyId) {
                throw ProductNotFound::withId($productId);
            }

            if ($isActive !== null) {
                $product->setActive($isActive);
            }

            if ($updateStock) {
                $product->setStockQuantity($stockQuantity);
            }

            $this->products->save($product);

            return $product;
        });
    }
}
