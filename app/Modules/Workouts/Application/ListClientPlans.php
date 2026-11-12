<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Список активных личных планов клиента (Бизнес-ТЗ 9; D-017).
 */
final class ListClientPlans
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutPlanAssembler $assembler,
    ) {}

    /**
     * @return list<WorkoutPlanView>
     */
    public function execute(int $userId): array
    {
        return $this->assembler->buildViews($this->plans->listForClient($userId));
    }
}
