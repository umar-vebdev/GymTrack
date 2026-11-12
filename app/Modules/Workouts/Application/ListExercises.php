<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;

/**
 * Просмотр справочника упражнений для платформы и клиента (Бизнес-ТЗ 9; D-016).
 */
final class ListExercises
{
    public function __construct(
        private readonly ExerciseRepositoryInterface $exercises,
    ) {}

    /**
     * @return list<Exercise>
     */
    public function forPlatform(?MuscleGroup $muscleGroup = null, ?string $query = null, ?bool $onlyActive = null): array
    {
        return $this->exercises->listForPlatform($muscleGroup, $query, $onlyActive);
    }

    /**
     * @return list<Exercise>
     */
    public function forClient(int $userId, ?MuscleGroup $muscleGroup = null, ?string $query = null): array
    {
        return $this->exercises->listForClient($userId, $muscleGroup, $query);
    }
}

