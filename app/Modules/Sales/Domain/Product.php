<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use App\Shared\Domain\Money;
use DomainException;

/**
 * Доменная сущность товара (вода, спортпит, аренда полотенца и т. д.).
 * Остаток (stockQuantity) опционален: null означает, что учёт остатков не ведётся.
 */
final class Product
{
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly string $name,
        private readonly Money $price,
        private ?int $stockQuantity,
        private bool $isActive
    ) {
        self::assertValidStock($stockQuantity);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function stockQuantity(): ?int
    {
        return $this->stockQuantity;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setStockQuantity(?int $stockQuantity): void
    {
        self::assertValidStock($stockQuantity);
        $this->stockQuantity = $stockQuantity;
    }

    public function decrementStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Количество списываемого товара должно быть положительным.');
        }

        if ($this->stockQuantity === null) {
            return;
        }

        if ($this->stockQuantity < $quantity) {
            throw new DomainException(sprintf(
                'Недостаточно товара «%s» на складе (доступно: %d, запрошено: %d).',
                $this->name,
                $this->stockQuantity,
                $quantity
            ));
        }

        $this->stockQuantity -= $quantity;
    }

    public function incrementStock(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Количество возвращаемого товара должно быть положительным.');
        }

        if ($this->stockQuantity === null) {
            return;
        }

        $this->stockQuantity += $quantity;
    }

    private static function assertValidStock(?int $stockQuantity): void
    {
        if ($stockQuantity !== null && $stockQuantity < 0) {
            throw new DomainException('Остаток товара не может быть отрицательным.');
        }
    }
}
