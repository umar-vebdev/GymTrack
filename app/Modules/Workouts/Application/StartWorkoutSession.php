<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutSession;
use App\Modules\Workouts\Domain\Exceptions\WorkoutResourceNotFound;
use App\Modules\Workouts\Domain\WorkoutPlan;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;
use App\Shared\Domain\Clock;

/**
 * Старт новой тренировочной сессии клиента (по плану или свободной; Бизнес-ТЗ 9, D-018).
 */
final class StartWorkoutSession
{
    public function __construct(
        private readonly WorkoutSessionRepositoryInterface $sessions,
        private readonly WorkoutPlanRepositoryInterface $plans,
        private readonly WorkoutSessionAssembler $assembler,
        private readonly Clock $clock,
    ) {}

    public function execute(StartWorkoutSessionCommand $command): WorkoutSessionView
    {
        $title = $command->title;

        if ($command->planId !== null) {
            $plan = $this->findAccessibleActivePlan($command->userId, $command->planId);

            if ($title === null || trim($title) === '') {
                $title = $plan->title();
            }
        } elseif ($title === null || trim($title) === '') {
            throw InvalidWorkoutSession::titleRequired();
        }

        $session = $this->sessions->start(
            userId: $command->userId,
            planId: $command->planId,
            title: $title,
            startedAt: $this->clock->now(),
        );

        return $this->assembler->buildView($session);
    }

    private function findAccessibleActivePlan(int $userId, int $planId): WorkoutPlan
    {
        $owned = $this->plans->findOwnedById($userId, $planId);
        if ($owned !== null && $owned->isActive()) {
            return $owned;
        }

        $library = $this->plans->findPublishedLibraryById($planId);
        if ($library !== null) {
            return $library;
        }

        throw WorkoutResourceNotFound::plan($planId);
    }
}

