<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutPlanItem;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;

/**
 * Копирование готового плана из библиотеки платформы в собственные планы клиента (D-017).
 */
final class CopyLibraryPlanToClient
{
    public function __construct(
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutPlanAssembler $assembler,
    ) {}

    public function execute(int $userId, int $libraryPlanId, ?string $title = null): WorkoutPlanView
    {
        $libraryPlan = $this->plans->findPublishedLibraryById($libraryPlanId);

        if ($libraryPlan === null) {
            throw WorkoutResourceNotFound::plan($libraryPlanId);
        }

        $copiedItems = array_map(
            static fn (WorkoutPlanItem $item): WorkoutPlanItem => new WorkoutPlanItem(
                id: 0,
                exerciseId: $item->exerciseId(),
                sortOrder: $item->sortOrder(),
                targetSets: $item->targetSets(),
                targetReps: $item->targetReps(),
                targetWeightGrams: $item->targetWeightGrams(),
                restSeconds: $item->restSeconds(),
            ),
            $libraryPlan->items(),
        );

        $copy = $this->plans->create(
            userId: $userId,
            sourcePlanId: $libraryPlan->id(),
            title: $title ?? $libraryPlan->title(),
            description: $libraryPlan->description(),
            level: $libraryPlan->level(),
            isPublished: false,
            items: $copiedItems,
        );

        return $this->assembler->buildView($copy);
    }
}

