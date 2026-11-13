<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

use DateTimeImmutable;

interface WorkoutSessionRepositoryInterface
{
    public function start(
        int $userId,
        ?int $planId,
        string $title,
        DateTimeImmutable $startedAt,
    ): WorkoutSession;

    public function addSet(
        int $userId,
        int $sessionId,
        int $exerciseId,
        int $weightGrams,
        int $reps,
        DateTimeImmutable $performedAt,
    ): ?WorkoutSession;

    public function save(WorkoutSession $session): WorkoutSession;

    public function findOwnedById(int $userId, int $id): ?WorkoutSession;

    /**
     * Список тренировок клиента (по дате начала от новых к старым).
     *
     * @return list<WorkoutSession>
     */
    public function listForClient(int $userId, ?WorkoutSessionStatus $status = null): array;

    /**
     * История тренировок по конкретному упражнению для отслеживания прогресса.
     *
     * @return list<ExerciseProgressSession>
     */
    public function listExerciseProgress(int $userId, int $exerciseId): array;
}
