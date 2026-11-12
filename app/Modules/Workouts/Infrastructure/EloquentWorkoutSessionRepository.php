<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Infrastructure;

use App\Modules\Workouts\Domain\ExerciseProgressSession;
use App\Modules\Workouts\Domain\WorkoutSession;
use App\Modules\Workouts\Domain\WorkoutSessionRepositoryInterface;
use App\Modules\Workouts\Domain\WorkoutSessionStatus;
use App\Modules\Workouts\Domain\WorkoutSet;
use App\Modules\Workouts\Infrastructure\Persistence\WorkoutSessionModel;
use App\Modules\Workouts\Infrastructure\Persistence\WorkoutSetModel;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

final class EloquentWorkoutSessionRepository implements WorkoutSessionRepositoryInterface
{
    public function start(
        int $userId,
        ?int $planId,
        string $title,
        DateTimeImmutable $startedAt,
    ): WorkoutSession {
        $utcStart = $this->toUtc($startedAt);

        $validated = new WorkoutSession(
            id: 0,
            publicId: '',
            userId: $userId,
            planId: $planId,
            title: $title,
            status: WorkoutSessionStatus::InProgress,
            startedAt: $utcStart,
        );

        $model = WorkoutSessionModel::query()->create([
            'user_id' => $validated->userId(),
            'plan_id' => $validated->planId(),
            'title' => $validated->title(),
            'status' => $validated->status()->value,
            'started_at' => CarbonImmutable::instance($validated->startedAt()),
            'finished_at' => null,
            'notes' => null,
        ]);

        return $this->toDomain($model->refresh()->load('sets'));
    }

    public function addSet(
        int $userId,
        int $sessionId,
        int $exerciseId,
        int $weightGrams,
        int $reps,
        DateTimeImmutable $performedAt,
    ): ?WorkoutSession {
        $utcPerformed = $this->toUtc($performedAt);

        /** @var ?WorkoutSession */
        return DB::transaction(function () use ($userId, $sessionId, $exerciseId, $weightGrams, $reps, $utcPerformed): ?WorkoutSession {
            $model = WorkoutSessionModel::query()
                ->where('user_id', $userId)
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if ($model === null) {
                return null;
            }

            $model->load('sets');
            $session = $this->toDomain($model);
            $newSet = $session->logSet($exerciseId, $weightGrams, $reps, $utcPerformed);

            WorkoutSetModel::query()->create([
                'session_id' => $session->id(),
                'exercise_id' => $newSet->exerciseId(),
                'set_number' => $newSet->setNumber(),
                'weight_grams' => $newSet->weightGrams(),
                'reps' => $newSet->reps(),
                'performed_at' => CarbonImmutable::instance($newSet->performedAt()),
            ]);

            $model->load('sets');

            return $this->toDomain($model);
        });
    }

    public function save(WorkoutSession $session): WorkoutSession
    {
        WorkoutSessionModel::query()
            ->where('user_id', $session->userId())
            ->whereKey($session->id())
            ->update([
                'title' => $session->title(),
                'status' => $session->status()->value,
                'finished_at' => $session->finishedAt() !== null
                    ? CarbonImmutable::instance($this->toUtc($session->finishedAt()))
                    : null,
                'notes' => $session->notes(),
            ]);

        /** @var WorkoutSessionModel $model */
        $model = WorkoutSessionModel::query()
            ->with('sets')
            ->findOrFail($session->id());

        return $this->toDomain($model);
    }

    public function findOwnedById(int $userId, int $id): ?WorkoutSession
    {
        $model = WorkoutSessionModel::query()
            ->with('sets')
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function listForClient(int $userId, ?WorkoutSessionStatus $status = null): array
    {
        $builder = WorkoutSessionModel::query()
            ->with('sets')
            ->where('user_id', $userId);

        if ($status !== null) {
            $builder->where('status', $status->value);
        }

        return array_values(
            $builder->orderByDesc('started_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (WorkoutSessionModel $model): WorkoutSession => $this->toDomain($model))
                ->all(),
        );
    }

    public function listExerciseProgress(int $userId, int $exerciseId): array
    {
        $sessions = WorkoutSessionModel::query()
            ->where('user_id', $userId)
            ->where('status', '!=', WorkoutSessionStatus::Cancelled->value)
            ->whereHas('sets', static fn ($q) => $q->where('exercise_id', $exerciseId))
            ->with(['sets' => static function (Relation $q) use ($exerciseId): void {
                $q->getQuery()
                    ->where('exercise_id', $exerciseId)
                    ->orderBy('set_number')
                    ->orderBy('id');
            }])
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        return array_values(
            $sessions->map(fn (WorkoutSessionModel $model): ExerciseProgressSession => new ExerciseProgressSession(
                sessionId: $model->id,
                sessionPublicId: $model->public_id,
                sessionTitle: $model->title,
                sessionStatus: WorkoutSessionStatus::from($model->status),
                startedAt: $this->toUtc($model->started_at),
                sets: $this->mapSets($model),
            ))->all(),
        );
    }

    private function toDomain(WorkoutSessionModel $model): WorkoutSession
    {
        return new WorkoutSession(
            id: $model->id,
            publicId: $model->public_id,
            userId: $model->user_id,
            planId: $model->plan_id,
            title: $model->title,
            status: WorkoutSessionStatus::from($model->status),
            startedAt: $this->toUtc($model->started_at),
            finishedAt: $model->finished_at !== null ? $this->toUtc($model->finished_at) : null,
            notes: $model->notes,
            sets: $this->mapSets($model),
        );
    }

    /**
     * @return list<WorkoutSet>
     */
    private function mapSets(WorkoutSessionModel $model): array
    {
        /** @var list<WorkoutSet> */
        return $model->sets
            ->map(fn (WorkoutSetModel $set): WorkoutSet => new WorkoutSet(
                id: $set->id,
                exerciseId: $set->exercise_id,
                setNumber: $set->set_number,
                weightGrams: $set->weight_grams,
                reps: $set->reps,
                performedAt: $this->toUtc($set->performed_at),
            ))
            ->values()
            ->all();
    }

    private function toUtc(DateTimeImmutable $dateTime): DateTimeImmutable
    {
        return $dateTime->setTimezone(new DateTimeZone('UTC'));
    }
}
