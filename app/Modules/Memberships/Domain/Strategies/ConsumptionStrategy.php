<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Strategies;

use App\Modules\Memberships\Domain\Membership;
use DateTimeImmutable;

/**
 * Паттерн Strategy: алгоритм списания для конкретного вида абонемента.
 */
interface ConsumptionStrategy
{
    public function consume(Membership $membership, DateTimeImmutable $at): void;
}
