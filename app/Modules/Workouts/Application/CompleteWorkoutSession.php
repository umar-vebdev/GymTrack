<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;
use App\Shared\Domain\Clock;

/**
 * Завершение или отмена тренировочной сессии клиента (Бизнес-ТЗ 9; D-018).
 */
final class CompleteWorkoutSession
{
    public function __construct(
        private readonly WorkoutSessionRepositoryInterface $sessions,
        private readonly WorkoutSessionAssembler $assembler,
        private readonly Clock $clock,
    ) {}

    public function execute(CompleteWorkoutSessionCommand $command): WorkoutSessionView
    {
        $session = $this->sessions->findOwnedById($command->userId, $command->sessionId);

        if ($session === null) {
            throw WorkoutResourceNotFound::session($command->sessionId);
        }

        $now = $this->clock->now();
        if ($command->cancel) {
            $session->cancel($now, $command->notes);
        } else {
            $session->complete($now, $command->notes);
        }

        $saved = $this->sessions->save($session);

        return $this->assembler->buildView($saved);
    }
}

