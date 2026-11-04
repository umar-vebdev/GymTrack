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

        return $model ? new Gym(
            id: $model->id,
            companyId: $model->company_id,
            name: $model->name,
            address: $model->address
        ) : null;
    }

    public function create(int $companyId, string $name, ?string $address): int
    {
        $model = GymModel::create([
            'company_id' => $companyId,
            'name' => $name,
            'address' => $address,
        ]);

        return $model->id;
    }

    public function findByIdForUpdate(int $id): ?Gym
    {
        $model = GymModel::query()->lockForUpdate()->find($id);

        return $model ? new Gym($model->id, $model->company_id, $model->name, $model->address) : null;
    }
}
