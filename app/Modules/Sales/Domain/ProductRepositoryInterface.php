<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use App\Shared\Domain\Money;

interface ProductRepositoryInterface
{
    public function findById(int $id): ?Product;

    /**
     * Загружает товар с блокировкой строки (`SELECT ... FOR UPDATE`) внутри транзакции БД.
     */
    public function findByIdForUpdate(int $id): ?Product;

    public function create(
        int $companyId,
        string $name,
        Money $price,
        ?int $stockQuantity
    ): int;

    /**
     * @return list<Product>
     */
    public function listByCompany(int $companyId, ?bool $onlyActive = null): array;

    public function save(Product $product): void;
}
