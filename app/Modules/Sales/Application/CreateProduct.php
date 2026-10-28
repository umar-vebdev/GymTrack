<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Shared\Domain\Money;

final class CreateProduct
{
    private const DEFAULT_CURRENCY = 'TJS';

    public function __construct(
        private readonly ProductRepositoryInterface $products
    ) {}

    public function execute(
        int $companyId,
        string $name,
        int $priceMinor,
        ?int $stockQuantity = null,
        ?string $currency = null
    ): int {
        return $this->products->create(
            companyId: $companyId,
            name: $name,
            price: new Money($priceMinor, $currency ?? self::DEFAULT_CURRENCY),
            stockQuantity: $stockQuantity
        );
    }
}
