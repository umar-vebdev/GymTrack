<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;

/**
 * Просмотр деталей одной тренировочной сессии клиента (D-018).
 */
final class GetWorkoutSession
{
    public function __construct(
        private readonly WorkoutSessionRepositoryInterface $sessions,
        private readonly WorkoutSessionAssembler $assembler,
    ) {}

    public function execute(int $userId, int $sessionId): WorkoutSessionView
    {
        $session = $this->sessions->findOwnedById($userId, $sessionId);

        if ($session === null) {
            throw WorkoutResourceNotFound::session($sessionId);
        }

        return $this->assembler->buildView($session);
    }
}

