<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Список готовых планов платформы (для админа платформы и библиотеки клиента).
 */
final class ListLibraryPlans
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutPlanAssembler $assembler,
    ) {}

    /**
     * @return list<WorkoutPlanView>
     */
    public function forPlatform(
        ?PlanLevel $level = null,
        ?string $query = null,
        ?bool $onlyPublished = null,
        ?bool $onlyActive = null,
    ): array {
        return $this->assembler->buildViews(
            $this->plans->listForPlatform($level, $query, $onlyPublished, $onlyActive),
        );
    }

    /**
     * @return list<WorkoutPlanView>
     */
    public function forClientLibrary(?PlanLevel $level = null, ?string $query = null): array
    {
        return $this->assembler->buildViews(
            $this->plans->listLibraryForClient($level, $query),
        );
    }
}

