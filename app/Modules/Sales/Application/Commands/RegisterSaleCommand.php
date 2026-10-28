<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application\Commands;

use App\Modules\Sales\Domain\PaymentMethod;

final class RegisterSaleCommand
{
    /**
     * @param  list<array{product_id: int, quantity: int}>  $products
     * @param  list<int>  $membershipTypeIds
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $acceptedByUserId,
        public readonly ?int $clientId,
        public readonly PaymentMethod $paymentMethod,
        public readonly array $products = [],
        public readonly array $membershipTypeIds = []
    ) {}
}
