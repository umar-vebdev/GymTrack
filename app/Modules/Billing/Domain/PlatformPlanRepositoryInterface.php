<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain;

use App\Shared\Domain\Money;

interface PlatformPlanRepositoryInterface
{
    public function findById(int $id): ?PlatformPlan;

    public function create(
        string $name,
        BillingPeriod $period,
        Money $price,
        int $maxGyms,
        int $maxStaff,
        bool $isActive = true
    ): int;

    /**
     * @return list<PlatformPlan>
     */
    public function listAll(bool $onlyActive = false): array;

    public function save(PlatformPlan $plan): void;
}

