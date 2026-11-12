<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Мягкое архивирование личного плана клиента (без удаления ссылок из истории тренировок; D-017).
 */
final class ArchiveClientPlan
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
    ) {}

    public function execute(int $userId, int $planId): void
    {
        $plan = $this->plans->findOwnedById($userId, $planId);

        if ($plan === null) {
            throw WorkoutResourceNotFound::plan($planId);
        }

        $plan->deactivate();
        $this->plans->save($plan);
    }
}
