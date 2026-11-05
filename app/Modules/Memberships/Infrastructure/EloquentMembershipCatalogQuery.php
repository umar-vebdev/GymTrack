<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Infrastructure;

use App\Modules\Memberships\Contracts\MembershipCatalogQueryInterface;
use App\Modules\Memberships\Contracts\PublicMembershipType;
use App\Modules\Memberships\Infrastructure\Persistence\MembershipTypeModel;

final class EloquentMembershipCatalogQuery implements MembershipCatalogQueryInterface
{
    public function listActiveForCompany(int $companyId): array
    {
        /** @var list<PublicMembershipType> $items */
        $items = MembershipTypeModel::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('public_id', 'asc')
            ->get()
            ->map(static fn (MembershipTypeModel $model): PublicMembershipType => new PublicMembershipType(
                publicId: $model->public_id,
                name: $model->name,
                kind: $model->kind,
                startMode: $model->start_mode,
                priceMinor: (int) $model->price_minor,
                currency: $model->currency,
                visitsCount: $model->visits_count !== null ? (int) $model->visits_count : null,
                durationDays: $model->duration_days !== null ? (int) $model->duration_days : null,
                validityDays: $model->validity_days !== null ? (int) $model->validity_days : null,
                dailyLimit: (int) $model->daily_limit,
                timeFromMinute: $model->time_from_minute !== null ? (int) $model->time_from_minute : null,
                timeToMinute: $model->time_to_minute !== null ? (int) $model->time_to_minute : null,
                freezeMaxDays: (int) $model->freeze_max_days,
                freezeMaxTimes: (int) $model->freeze_max_times,
            ))
            ->values()
            ->all();

        return $items;
    }

    public function findCompanyIdsByPriceRange(string $currency, ?int $priceMin = null, ?int $priceMax = null): array
    {
        $normalizedCurrency = strtoupper(trim($currency));
        if ($normalizedCurrency === '') {
            return [];
        }

        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            return [];
        }

        $query = MembershipTypeModel::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->whereRaw('UPPER(currency) = ?', [$normalizedCurrency]);

        if ($priceMin !== null) {
            $query->where('price_minor', '>=', $priceMin);
        }

        if ($priceMax !== null) {
            $query->where('price_minor', '<=', $priceMax);
        }

        /** @var list<int> $companyIds */
        $companyIds = $query
            ->select('company_id')
            ->distinct()
            ->orderBy('company_id', 'asc')
            ->pluck('company_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return $companyIds;
    }
}

