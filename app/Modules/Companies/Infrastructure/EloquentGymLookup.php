<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;

final class EloquentGymLookup implements GymLookupInterface
{
    public function existsInCompany(int $gymId, int $companyId): bool
    {
        return GymModel::query()
            ->withoutGlobalScopes()
            ->whereKey($gymId)
            ->where('company_id', $companyId)
            ->exists();
    }
}
