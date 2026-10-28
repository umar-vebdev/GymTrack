<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

use App\Shared\Domain\Money;

/**
 * DTO с данными типа абонемента для расчёта чека в модуле Sales.
 */
final class MembershipTypeQuote
{
    public function __construct(
        public readonly int $typeId,
        public readonly string $name,
        public readonly Money $price
    ) {}
}
