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
            name: $model->name,
            currencyId: $model->currency_id
        ) : null;
    }

    public function create(int $ownerId, string $name, int $currencyId = 1): int
    {
        $model = CompanyModel::create([
            'owner_id' => $ownerId,
            'name' => $name,
            'currency_id' => $currencyId,
        ]);

        return $model->id;
    }

    public function updateCurrency(int $companyId, int $currencyId): void
    {
        CompanyModel::where('id', $companyId)->update([
            'currency_id' => $currencyId,
        ]);
    }
}
