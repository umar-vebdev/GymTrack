<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Обновление собственного плана клиента (Бизнес-ТЗ 9; D-017).
 */
final class UpdateClientPlan
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutPlanAssembler $assembler,
    ) {}

    public function execute(UpdateClientPlanCommand $command): WorkoutPlanView
    {
        $plan = $this->plans->findOwnedById($command->userId, $command->planId);

        if ($plan === null) {
            throw WorkoutResourceNotFound::plan($command->planId);
        }

        $items = $this->assembler->resolveClientItems($command->userId, $command->items);
        $plan->update($command->title, $command->description, $command->level, $items);

        $saved = $this->plans->save($plan);

        return $this->assembler->buildView($saved);
    }
}
