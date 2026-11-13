<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Domain\Gym;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;

final class EloquentGymRepository implements GymRepositoryInterface
{
    public function findById(int $id): ?Gym
    {
        $model = GymModel::query()->withoutGlobalScopes()->find($id);

        return $model ? $this->mapToDomain($model) : null;
    }

    public function findByIdForUpdate(int $id): ?Gym
    {
        $model = GymModel::query()->withoutGlobalScopes()->lockForUpdate()->find($id);

        return $model ? $this->mapToDomain($model) : null;
    }

    /**
     * @return list<Gym>
     */
    public function listByCompany(int $companyId): array
    {
        return array_values(
            GymModel::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->get()
                ->map(fn (GymModel $model): Gym => $this->mapToDomain($model))
                ->all()
        );
    }

    public function create(int $companyId, string $name, ?string $address, string $timezone = Gym::DEFAULT_TIMEZONE): int
    {
        $model = GymModel::create([
            'company_id' => $companyId,
            'name' => $name,
            'address' => $address,
            'timezone' => $timezone,
        ]);

        return $model->id;
    }

    public function save(Gym $gym): void
    {
        GymModel::query()
            ->withoutGlobalScopes()
            ->where('id', $gym->id())
            ->where('company_id', $gym->companyId())
            ->update([
                'name' => $gym->name(),
                'address' => $gym->address(),
                'timezone' => $gym->timezone(),
            ]);
    }

    private function mapToDomain(GymModel $model): Gym
    {
        return new Gym(
            id: $model->id,
            companyId: $model->company_id,
            name: $model->name,
            address: $model->address,
            timezone: $model->timezone ?: Gym::DEFAULT_TIMEZONE,
        );
    }
}
