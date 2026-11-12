<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Infrastructure;

use App\Modules\Workouts\Domain\PlanLevel;
use App\Modules\Workouts\Domain\WorkoutPlan;
use App\Modules\Workouts\Domain\WorkoutPlanItem;
use App\Modules\Workouts\Domain\WorkoutPlanRepositoryInterface;
use App\Modules\Workouts\Infrastructure\Persistence\WorkoutPlanItemModel;
use App\Modules\Workouts\Infrastructure\Persistence\WorkoutPlanModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentWorkoutPlanRepository implements WorkoutPlanRepositoryInterface
{
    public function create(
        ?int $userId,
        ?int $sourcePlanId,
        string $title,
        ?string $description,
        PlanLevel $level,
        bool $isPublished,
        array $items,
    ): WorkoutPlan {
        // Проверяем все доменные инварианты до записи в БД.
        $validated = new WorkoutPlan(
            id: 0,
            publicId: '',
            userId: $userId,
            sourcePlanId: $sourcePlanId,
            title: $title,
            description: $description,
            level: $level,
            isPublished: $isPublished,
            isActive: true,
            items: $items,
        );

        /** @var WorkoutPlan */
        return DB::transaction(function () use ($validated): WorkoutPlan {
            $model = WorkoutPlanModel::query()->create([
                'user_id' => $validated->userId(),
                'source_plan_id' => $validated->sourcePlanId(),
                'title' => $validated->title(),
                'description' => $validated->description(),
                'level' => $validated->level()->value,
                'is_published' => $validated->isPublished(),
                'is_active' => $validated->isActive(),
            ]);

            $this->syncItems($model->id, $validated->items());
            $model->refresh()->load('items');

            return $this->toDomain($model);
        });
    }

    public function save(WorkoutPlan $plan): WorkoutPlan
    {
        /** @var WorkoutPlan */
        return DB::transaction(function () use ($plan): WorkoutPlan {
            $query = WorkoutPlanModel::query()->whereKey($plan->id());

            if ($plan->userId() === null) {
                $query->whereNull('user_id');
            } else {
                $query->where('user_id', $plan->userId());
            }

            $query->update([
                'title' => $plan->title(),
                'description' => $plan->description(),
                'level' => $plan->level()->value,
                'is_published' => $plan->isPublished(),
                'is_active' => $plan->isActive(),
            ]);

            $this->syncItems($plan->id(), $plan->items());

            /** @var WorkoutPlanModel $model */
            $model = WorkoutPlanModel::query()
                ->with('items')
                ->findOrFail($plan->id());

            return $this->toDomain($model);
        });
    }

    public function findPlatformById(int $id): ?WorkoutPlan
    {
        $model = WorkoutPlanModel::query()
            ->with('items')
            ->whereNull('user_id')
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findPublishedLibraryById(int $id): ?WorkoutPlan
    {
        $model = WorkoutPlanModel::query()
            ->with('items')
            ->whereNull('user_id')
            ->where('is_published', true)
            ->where('is_active', true)
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findOwnedById(int $userId, int $id): ?WorkoutPlan
    {
        $model = WorkoutPlanModel::query()
            ->with('items')
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function listForPlatform(
        ?PlanLevel $level = null,
        ?string $query = null,
        ?bool $onlyPublished = null,
        ?bool $onlyActive = null,
    ): array {
        $builder = WorkoutPlanModel::query()
            ->with('items')
            ->whereNull('user_id');

        if ($onlyPublished !== null) {
            $builder->where('is_published', $onlyPublished);
        }
        if ($onlyActive !== null) {
            $builder->where('is_active', $onlyActive);
        }

        $this->applyFilters($builder, $level, $query);

        return array_values(
            $builder->orderByDesc('id')
                ->get()
                ->map(fn (WorkoutPlanModel $model): WorkoutPlan => $this->toDomain($model))
                ->all(),
        );
    }

    public function listLibraryForClient(?PlanLevel $level = null, ?string $query = null): array
    {
        $builder = WorkoutPlanModel::query()
            ->with('items')
            ->whereNull('user_id')
            ->where('is_published', true)
            ->where('is_active', true);

        $this->applyFilters($builder, $level, $query);

        return array_values(
            $builder->orderBy('title')
                ->orderBy('id')
                ->get()
                ->map(fn (WorkoutPlanModel $model): WorkoutPlan => $this->toDomain($model))
                ->all(),
        );
    }

    public function listForClient(int $userId): array
    {
        return array_values(
            WorkoutPlanModel::query()
                ->with('items')
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->get()
                ->map(fn (WorkoutPlanModel $model): WorkoutPlan => $this->toDomain($model))
                ->all(),
        );
    }

    /**
     * @param  list<WorkoutPlanItem>  $items
     */
    private function syncItems(int $planId, array $items): void
    {
        WorkoutPlanItemModel::query()
            ->where('plan_id', $planId)
            ->delete();

        foreach ($items as $item) {
            WorkoutPlanItemModel::query()->create([
                'plan_id' => $planId,
                'exercise_id' => $item->exerciseId(),
                'sort_order' => $item->sortOrder(),
                'target_sets' => $item->targetSets(),
                'target_reps' => $item->targetReps(),
                'target_weight_grams' => $item->targetWeightGrams(),
                'rest_seconds' => $item->restSeconds(),
            ]);
        }
    }

    /**
     * @param  Builder<WorkoutPlanModel>  $builder
     */
    private function applyFilters(Builder $builder, ?PlanLevel $level, ?string $query): void
    {
        if ($level !== null) {
            $builder->where('level', $level->value);
        }

        if ($query !== null) {
            $trimmed = trim($query);
            if ($trimmed !== '') {
                $escaped = addcslashes(mb_strtolower($trimmed, 'UTF-8'), '\\%_');
                $builder->whereRaw('LOWER(title) LIKE ?', ['%'.$escaped.'%']);
            }
        }
    }

    private function toDomain(WorkoutPlanModel $model): WorkoutPlan
    {
        /** @var list<WorkoutPlanItem> $items */
        $items = $model->items
            ->map(static fn (WorkoutPlanItemModel $item): WorkoutPlanItem => new WorkoutPlanItem(
                id: $item->id,
                exerciseId: $item->exercise_id,
                sortOrder: $item->sort_order,
                targetSets: $item->target_sets,
                targetReps: $item->target_reps,
                targetWeightGrams: $item->target_weight_grams,
                restSeconds: $item->rest_seconds,
            ))
            ->values()
            ->all();

        return new WorkoutPlan(
            id: $model->id,
            publicId: $model->public_id,
            userId: $model->user_id,
            sourcePlanId: $model->source_plan_id,
            title: $model->title,
            description: $model->description,
            level: PlanLevel::from($model->level),
            isPublished: $model->is_published,
            isActive: $model->is_active,
            items: $items,
        );
    }
}

