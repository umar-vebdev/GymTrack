<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use App\Shared\Domain\Money;
use DateTimeImmutable;

/**
 * Value Object отметки об оплате (PaymentMark): способ, сумма, сотрудник и время приёма оплаты.
 */
final class PaymentMark
{
    public function __construct(
        public readonly PaymentMethod $method,
        public readonly Money $amount,
        public readonly int $acceptedByUserId,
        public readonly DateTimeImmutable $paidAt
    ) {}
}

