<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Infrastructure;

use App\Modules\Workouts\Domain\Exercise;
use App\Modules\Workouts\Domain\ExerciseRepositoryInterface;
use App\Modules\Workouts\Domain\MuscleGroup;
use App\Modules\Workouts\Infrastructure\Persistence\ExerciseModel;
use Illuminate\Database\Eloquent\Builder;

final class EloquentExerciseRepository implements ExerciseRepositoryInterface
{
    public function create(
        ?int $userId,
        string $name,
        MuscleGroup $muscleGroup,
        ?string $description = null,
    ): Exercise {
        $model = ExerciseModel::query()->create([
            'user_id' => $userId,
            'name' => Exercise::cleanName($name),
            'muscle_group' => $muscleGroup->value,
            'description' => Exercise::cleanDescription($description),
            'is_active' => true,
        ]);

        return $this->toDomain($model->refresh());
    }

    public function save(Exercise $exercise): void
    {
        $query = ExerciseModel::query()->whereKey($exercise->id());

        if ($exercise->userId() === null) {
            $query->whereNull('user_id');
        } else {
            $query->where('user_id', $exercise->userId());
        }

        $query->update([
            'name' => $exercise->name(),
            'muscle_group' => $exercise->muscleGroup()->value,
            'description' => $exercise->description(),
            'is_active' => $exercise->isActive(),
        ]);
    }

    public function findPlatformById(int $id): ?Exercise
    {
        $model = ExerciseModel::query()
            ->whereNull('user_id')
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findOwnedById(int $userId, int $id): ?Exercise
    {
        $model = ExerciseModel::query()
            ->where('user_id', $userId)
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findAvailableForClient(int $userId, int $id): ?Exercise
    {
        $model = ExerciseModel::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($userId): void {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            })
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findByIds(array $ids): array
    {
        $uniqueIds = array_values(array_unique(array_filter(
            $ids,
            static fn (int $id): bool => $id > 0,
        )));

        if ($uniqueIds === []) {
            return [];
        }

        /** @var array<int, Exercise> $map */
        $map = [];
        $models = ExerciseModel::query()->whereIn('id', $uniqueIds)->get();

        foreach ($models as $model) {
            $exercise = $this->toDomain($model);
            $map[$exercise->id()] = $exercise;
        }

        return $map;
    }

    public function listForPlatform(?MuscleGroup $muscleGroup = null, ?string $query = null, ?bool $onlyActive = null): array
    {
        $builder = ExerciseModel::query()->whereNull('user_id');

        if ($onlyActive !== null) {
            $builder->where('is_active', $onlyActive);
        }

        $this->applyFilters($builder, $muscleGroup, $query);

        return array_values(
            $builder->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (ExerciseModel $model): Exercise => $this->toDomain($model))
                ->all(),
        );
    }

    public function listForClient(int $userId, ?MuscleGroup $muscleGroup = null, ?string $query = null): array
    {
        $builder = ExerciseModel::query()
            ->where('is_active', true)
            ->where(function (Builder $q) use ($userId): void {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $userId);
            });

        $this->applyFilters($builder, $muscleGroup, $query);

        return array_values(
            $builder->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (ExerciseModel $model): Exercise => $this->toDomain($model))
                ->all(),
        );
    }

    /**
     * @param  Builder<ExerciseModel>  $builder
     */
    private function applyFilters(Builder $builder, ?MuscleGroup $muscleGroup, ?string $query): void
    {
        if ($muscleGroup !== null) {
            $builder->where('muscle_group', $muscleGroup->value);
        }

        if ($query !== null) {
            $trimmed = trim($query);
            if ($trimmed !== '') {
                $escaped = addcslashes(mb_strtolower($trimmed, 'UTF-8'), '\\%_');
                $builder->whereRaw('LOWER(name) LIKE ?', ['%'.$escaped.'%']);
            }
        }
    }

    private function toDomain(ExerciseModel $model): Exercise
    {
        return new Exercise(
            id: $model->id,
            publicId: $model->public_id,
            userId: $model->user_id,
            name: $model->name,
            muscleGroup: MuscleGroup::from($model->muscle_group),
            description: $model->description,
            isActive: $model->is_active,
        );
    }
}
