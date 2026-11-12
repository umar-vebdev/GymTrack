<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain;

use App\Modules\Workouts\Domain\Exceptions\InvalidWorkoutSession;
use DateTimeImmutable;

/**
 * Тренировочная сессия клиента (Бизнес-ТЗ 9; D-018).
 */
final class WorkoutSession
{
    public const TITLE_MAX = 100;

    public const NOTES_MAX = 1000;

    private string $title;

    private ?string $notes;

    /** @var list<WorkoutSet> */
    private array $sets;

    /**
     * @param  list<WorkoutSet>  $sets
     */
    public function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly int $userId,
        private readonly ?int $planId,
        string $title,
        private WorkoutSessionStatus $status,
        private readonly DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $finishedAt = null,
        ?string $notes = null,
        array $sets = [],
    ) {
        if ($userId <= 0) {
            throw InvalidWorkoutSession::invalidUserId();
        }
        if ($planId !== null && $planId <= 0) {
            throw InvalidWorkoutSession::invalidPlanId();
        }
        if ($finishedAt !== null && $finishedAt < $startedAt) {
            throw InvalidWorkoutSession::finishedBeforeStarted();
        }

        $this->title = self::cleanTitle($title);
        $this->notes = self::cleanNotes($notes);
        $this->sets = $sets;
    }

    public static function cleanTitle(string $title): string
    {
        $trimmed = trim($title);

        if ($trimmed === '') {
            throw InvalidWorkoutSession::titleRequired();
        }
        if (mb_strlen($trimmed) > self::TITLE_MAX) {
            throw InvalidWorkoutSession::titleTooLong(self::TITLE_MAX);
        }

        return $trimmed;
    }

    public static function cleanNotes(?string $notes): ?string
    {
        $trimmed = $notes !== null ? trim($notes) : null;

        if ($trimmed === null || $trimmed === '') {
            return null;
        }
        if (mb_strlen($trimmed) > self::NOTES_MAX) {
            throw InvalidWorkoutSession::notesTooLong(self::NOTES_MAX);
        }

        return $trimmed;
    }

    public function logSet(int $exerciseId, int $weightGrams, int $reps, DateTimeImmutable $performedAt): WorkoutSet
    {
        if (!$this->isInProgress()) {
            throw InvalidWorkoutSession::sessionNotInProgress();
        }

        $nextNumber = $this->nextSetNumberForExercise($exerciseId);
        $set = new WorkoutSet(
            id: 0,
            exerciseId: $exerciseId,
            setNumber: $nextNumber,
            weightGrams: $weightGrams,
            reps: $reps,
            performedAt: $performedAt,
        );

        $this->sets[] = $set;

        return $set;
    }

    public function complete(DateTimeImmutable $finishedAt, ?string $notes = null): void
    {
        $this->transitionToFinished(WorkoutSessionStatus::Completed, $finishedAt, $notes);
    }

    public function cancel(DateTimeImmutable $finishedAt, ?string $notes = null): void
    {
        $this->transitionToFinished(WorkoutSessionStatus::Cancelled, $finishedAt, $notes);
    }

    public function isInProgress(): bool
    {
        return $this->status === WorkoutSessionStatus::InProgress;
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->userId === $userId;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function publicId(): string
    {
        return $this->publicId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function planId(): ?int
    {
        return $this->planId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function status(): WorkoutSessionStatus
    {
        return $this->status;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    /**
     * @return list<WorkoutSet>
     */
    public function sets(): array
    {
        return $this->sets;
    }

    private function nextSetNumberForExercise(int $exerciseId): int
    {
        $max = 0;
        foreach ($this->sets as $set) {
            if ($set->exerciseId() === $exerciseId && $set->setNumber() > $max) {
                $max = $set->setNumber();
            }
        }

        return $max + 1;
    }

    private function transitionToFinished(WorkoutSessionStatus $target, DateTimeImmutable $finishedAt, ?string $notes): void
    {
        if (!$this->isInProgress()) {
            throw InvalidWorkoutSession::sessionNotInProgress();
        }
        if ($finishedAt < $this->startedAt) {
            throw InvalidWorkoutSession::finishedBeforeStarted();
        }

        $this->status = $target;
        $this->finishedAt = $finishedAt;
        if ($notes !== null) {
            $this->notes = self::cleanNotes($notes);
        }
    }
}

