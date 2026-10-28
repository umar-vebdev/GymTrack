<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Domain\Product;
use App\Modules\Sales\Domain\ProductRepositoryInterface;

final class ListProducts
{
    public function __construct(
        private readonly ProductRepositoryInterface $products
    ) {}

    /**
     * @return list<Product>
     */
    public function execute(int $companyId, ?bool $onlyActive = null): array
    {
        return $this->products->listByCompany($companyId, $onlyActive);
    }
}
