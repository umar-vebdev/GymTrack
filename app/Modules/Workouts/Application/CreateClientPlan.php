<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Создание собственного тренировочного плана клиента (Бизнес-ТЗ 9; D-017).
 */
final class CreateClientPlan
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutPlanAssembler $assembler,
    ) {}

    public function execute(CreateClientPlanCommand $command): WorkoutPlanView
    {
        $items = $this->assembler->resolveClientItems($command->userId, $command->items);

        $plan = $this->plans->create(
            userId: $command->userId,
            sourcePlanId: null,
            title: $command->title,
            description: $command->description,
            level: $command->level,
            isPublished: false,
            items: $items,
        );

        return $this->assembler->buildView($plan);
    }
}
