<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Просмотр деталей личного плана клиента (D-017).
 */
final class GetClientPlan
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutPlanAssembler $assembler,
    ) {}

    public function execute(int $userId, int $planId): WorkoutPlanView
    {
        $plan = $this->plans->findOwnedById($userId, $planId);

        if ($plan === null) {
            throw WorkoutResourceNotFound::plan($planId);
        }

        return $this->assembler->buildView($plan);
    }
}
