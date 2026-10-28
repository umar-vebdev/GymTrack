<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use App\Shared\Domain\Money;
use DomainException;

/**
 * Позиция в чеке продажи: либо товар (productId), либо выданный абонемент (membershipTypeId + issuedMembershipId).
 */
final class SaleItem
{
    public function __construct(
        public readonly string $name,
        public readonly Money $unitPrice,
        public readonly int $quantity,
        public readonly ?int $productId = null,
        public readonly ?int $membershipTypeId = null,
        public readonly ?int $issuedMembershipId = null
    ) {
        if (($productId === null) === ($membershipTypeId === null)) {
            throw new DomainException('Позиция продажи должна ссылаться ровно на один объект: товар или тип абонемента.');
        }

        if ($quantity < 1) {
            throw new DomainException('Количество в позиции продажи должно быть не меньше 1.');
        }

        if ($membershipTypeId !== null) {
            if ($quantity !== 1) {
                throw new DomainException('Абонемент оформляется отдельной позицией с количеством 1.');
            }

            if ($issuedMembershipId === null) {
                throw new DomainException('Для проданного абонемента обязательно указание выданного абонемента (issuedMembershipId).');
            }
        }
    }

    public static function forProduct(int $productId, string $name, Money $unitPrice, int $quantity = 1): self
    {
        return new self(
            name: $name,
            unitPrice: $unitPrice,
            quantity: $quantity,
            productId: $productId
        );
    }

    public static function forMembership(
        int $membershipTypeId,
        string $name,
        Money $unitPrice,
        int $issuedMembershipId
    ): self {
        return new self(
            name: $name,
            unitPrice: $unitPrice,
            quantity: 1,
            membershipTypeId: $membershipTypeId,
            issuedMembershipId: $issuedMembershipId
        );
    }

    public function isMembership(): bool
    {
        return $this->membershipTypeId !== null;
    }

    public function isProduct(): bool
    {
        return $this->productId !== null;
    }

    public function lineTotal(): Money
    {
        return new Money(
            $this->unitPrice->amount() * $this->quantity,
            $this->unitPrice->currency()
        );
    }
}

