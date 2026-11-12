<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSessionStatus;

/**
 * История тренировочных сессий клиента (Бизнес-ТЗ 9; D-018).
 */
final class ListWorkoutSessions
{
    public function __construct(
        private readonly WorkoutSessionRepositoryInterface $sessions,
        private readonly WorkoutSessionAssembler $assembler,
    ) {}

    /**
     * @return list<WorkoutSessionView>
     */
    public function execute(int $userId, ?WorkoutSessionStatus $status = null): array
    {
        return $this->assembler->buildViews(
            $this->sessions->listForClient($userId, $status),
        );
    }
}
