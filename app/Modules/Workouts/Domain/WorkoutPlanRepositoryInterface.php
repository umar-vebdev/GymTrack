<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

interface WorkoutPlanRepositoryInterface
{
    /**
     * @param  list<WorkoutPlanItem>  $items
     */
    public function create(
        ?int $userId,
        ?int $sourcePlanId,
        string $title,
        ?string $description,
        PlanLevel $level,
        bool $isPublished,
        array $items,
    ): WorkoutPlan;

    public function save(WorkoutPlan $plan): WorkoutPlan;

    public function findPlatformById(int $id): ?WorkoutPlan;

    public function findPublishedLibraryById(int $id): ?WorkoutPlan;

    public function findOwnedById(int $userId, int $id): ?WorkoutPlan;

    /**
     * Список планов платформы для админа, отсортированный по (id desc).
     *
     * @return list<WorkoutPlan>
     */
    public function listForPlatform(
        ?PlanLevel $level = null,
        ?string $query = null,
        ?bool $onlyPublished = null,
        ?bool $onlyActive = null,
    ): array;

    /**
     * Список опубликованных и активных планов библиотеки платформы для клиента.
     *
     * @return list<WorkoutPlan>
     */
    public function listLibraryForClient(?PlanLevel $level = null, ?string $query = null): array;

    /**
     * Список активных личных планов клиента.
     *
     * @return list<WorkoutPlan>
     */
    public function listForClient(int $userId): array;
}

