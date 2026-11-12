<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

interface ExerciseRepositoryInterface
{
    public function create(
        ?int $userId,
        string $name,
        MuscleGroup $muscleGroup,
        ?string $description = null,
    ): Exercise;

    public function save(Exercise $exercise): void;

    public function findPlatformById(int $id): ?Exercise;

    public function findOwnedById(int $userId, int $id): ?Exercise;

    /**
     * Ищет активное упражнение, доступное клиенту (платформенное или личное).
     */
    public function findAvailableForClient(int $userId, int $id): ?Exercise;

    /**
     * Список платформенных упражнений для админа платформы, отсортированный по (name, id).
     *
     * @return list<Exercise>
     */
    public function listForPlatform(?MuscleGroup $muscleGroup = null, ?string $query = null, ?bool $onlyActive = null): array;

    /**
     * Список активных платформенных и личных упражнений клиента, отсортированный по (name, id).
     *
     * @return list<Exercise>
     */
    public function listForClient(int $userId, ?MuscleGroup $muscleGroup = null, ?string $query = null): array;
}
