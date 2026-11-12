<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Просмотр деталей одного готового плана платформы.
 */
final class GetLibraryPlan
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutPlanAssembler $assembler,
    ) {}

    public function forPlatform(int $planId): WorkoutPlanView
    {
        $plan = $this->plans->findPlatformById($planId);

        if ($plan === null) {
            throw WorkoutResourceNotFound::plan($planId);
        }

        return $this->assembler->buildView($plan);
    }

    public function forClientLibrary(int $planId): WorkoutPlanView
    {
        $plan = $this->plans->findPublishedLibraryById($planId);

        if ($plan === null) {
            throw WorkoutResourceNotFound::plan($planId);
        }

        return $this->assembler->buildView($plan);
    }
}

