<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

use DateTimeImmutable;

/**
 * Агрегат подходов по конкретному упражнению внутри одной тренировочной сессии.
 */
final readonly class ExerciseProgressSession
{
    /**
     * @param  list<WorkoutSet>  $sets
     */
    public function __construct(
        public int $sessionId,
        public string $sessionPublicId,
        public string $sessionTitle,
        public WorkoutSessionStatus $sessionStatus,
        public DateTimeImmutable $startedAt,
        public array $sets,
    ) {}
}
