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

    public function findCompanyId(int $gymId): ?int
    {
        $companyId = GymModel::query()
            ->withoutGlobalScopes()
            ->whereKey($gymId)
            ->value('company_id');

        return $companyId !== null ? (int) $companyId : null;
    }

    public function findTimezone(int $gymId): ?string
    {
        $timezone = GymModel::query()
            ->withoutGlobalScopes()
            ->whereKey($gymId)
            ->value('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : null;
    }

    public function resolveId(int|string $idOrPublicId): ?int
    {
        if (is_int($idOrPublicId)) {
            $id = $idOrPublicId > 0
                ? GymModel::query()->withoutGlobalScopes()->whereKey($idOrPublicId)->value('id')
                : null;

            return is_numeric($id) ? (int) $id : null;
        }

        $trimmed = trim($idOrPublicId);
        if ($trimmed === '' || strlen($trimmed) > 26) {
            return null;
        }

        if (ctype_digit($trimmed) && strlen($trimmed) < 19) {
            $numericId = (int) $trimmed;
            $id = $numericId > 0
                ? GymModel::query()->withoutGlobalScopes()->whereKey($numericId)->value('id')
                : null;

            return is_numeric($id) ? (int) $id : null;
        }

        $id = GymModel::query()->withoutGlobalScopes()->where('public_id', $trimmed)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }
}
