<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application;

use App\Modules\Billing\Domain\PlatformPlan;
use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;

final class ListPlatformPlans
{
    public function __construct(
        private readonly PlatformPlanRepositoryInterface $plans
    ) {}

    /**
     * @return list<PlatformPlan>
     */
    public function execute(bool $onlyActive = true): array
    {
        return $this->plans->listAll($onlyActive);
    }
}

