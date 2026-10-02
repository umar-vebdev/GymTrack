<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Domain\Company;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\CompanyModel;

final class EloquentCompanyRepository implements CompanyRepositoryInterface
{
    public function findById(int $id): ?Company
    {
        $model = CompanyModel::find($id);

        return $model ? new Company(
            id: $model->id,
            ownerId: $model->owner_id,
            name: $model->name
        ) : null;
    }

    public function create(int $ownerId, string $name): int
    {
        $model = CompanyModel::create([
            'owner_id' => $ownerId,
            'name' => $name,
        ]);

        return $model->id;
    }
}
