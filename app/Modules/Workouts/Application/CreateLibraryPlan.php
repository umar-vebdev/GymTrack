<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Создание готового тренировочного плана в библиотеке платформы (Бизнес-ТЗ 2, 9; D-016).
 */
final class CreateLibraryPlan
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutPlanAssembler $assembler,
    ) {}

    public function execute(CreateLibraryPlanCommand $command): WorkoutPlanView
    {
        $items = $this->assembler->resolvePlatformItems($command->items);

        $plan = $this->plans->create(
            userId: null,
            sourcePlanId: null,
            title: $command->title,
            description: $command->description,
            level: $command->level,
            isPublished: $command->isPublished,
            items: $items,
        );

        return $this->assembler->buildView($plan);
    }
}

