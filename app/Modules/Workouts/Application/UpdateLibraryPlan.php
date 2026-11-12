<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Обновление готового плана платформы (состав, метаданные, публикация, активность).
 */
final class UpdateLibraryPlan
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutPlanAssembler $assembler,
    ) {}

    public function execute(UpdateLibraryPlanCommand $command): WorkoutPlanView
    {
        $plan = $this->plans->findPlatformById($command->planId);

        if ($plan === null) {
            throw WorkoutResourceNotFound::plan($command->planId);
        }

        $items = $this->assembler->resolvePlatformItems($command->items);
        $plan->update($command->title, $command->description, $command->level, $items);

        if ($command->isPublished === true) {
            $plan->publish();
        } elseif ($command->isPublished === false) {
            $plan->unpublish();
        }

        if ($command->isActive === true) {
            $plan->activate();
        } elseif ($command->isActive === false) {
            $plan->deactivate();
        }

        $saved = $this->plans->save($plan);

        return $this->assembler->buildView($saved);
    }
}
