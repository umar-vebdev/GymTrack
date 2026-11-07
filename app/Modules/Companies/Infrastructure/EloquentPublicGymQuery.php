<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Contracts\GeoBounds;
use App\Modules\Companies\Contracts\GeoRadius;
use App\Modules\Companies\Contracts\PublicGymFilter;
use App\Modules\Companies\Contracts\PublicGymProfile;
use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Infrastructure\Persistence\GymPhotoModel;
use App\Modules\Companies\Infrastructure\Persistence\GymProfileModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

final class EloquentPublicGymQuery implements PublicGymQueryInterface
{
    private const EARTH_RADIUS_KM = 6371.0;

    public function search(PublicGymFilter $filter): array
    {
        if ($filter->companyIds !== null && $filter->companyIds === []) {
            return ['items' => [], 'total' => 0];
        }

        $query = $this->publishedProfilesQuery();

        if ($filter->companyIds !== null) {
            $query->whereIn('companies_gym_profiles.company_id', $filter->companyIds);
        }

        if ($filter->query !== null) {
            $escaped = addcslashes(mb_strtolower($filter->query, 'UTF-8'), '\\%_');
            $query->whereRaw('LOWER(gyms.name) LIKE ?', ['%'.$escaped.'%']);
        }

        if ($filter->district !== null) {
            $query->whereNotNull('companies_gym_profiles.district')
                ->whereRaw('LOWER(BTRIM(companies_gym_profiles.district)) = ?', [
                    mb_strtolower($filter->district, 'UTF-8'),
                ]);
        }

        foreach ($filter->amenities as $amenity) {
            $query->whereJsonContains('companies_gym_profiles.amenities', $amenity);
        }

        if ($filter->bounds !== null) {
            $this->applyBounds($query, $filter->bounds);
        }

        if ($filter->radius !== null) {
            $this->applyRadius($query, $filter->radius);
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            return ['items' => [], 'total' => 0];
        }

        $models = $query
            ->orderBy('gyms.public_id', 'asc')
            ->forPage($filter->page, $filter->perPage)
            ->get();

        /** @var list<int> $gymIds */
        $gymIds = $models->map(static fn (GymProfileModel $model): int => (int) $model->gym_id)->values()->all();
        $photosByGym = $this->loadPhotosByGymIds($gymIds);

        /** @var list<PublicGymProfile> $items */
        $items = $models
            ->map(fn (GymProfileModel $model): PublicGymProfile => $this->toPublicProfile(
                $model,
                $photosByGym[(int) $model->gym_id] ?? [],
            ))
            ->values()
            ->all();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function findByPublicId(string $publicId): ?PublicGymProfile
    {
        $normalizedId = trim($publicId);
        if ($normalizedId === '') {
            return null;
        }

        $model = $this->publishedProfilesQuery()
            ->where('gyms.public_id', $normalizedId)
            ->first();

        if ($model === null) {
            return null;
        }

        $gymId = (int) $model->gym_id;
        $photosByGym = $this->loadPhotosByGymIds([$gymId]);

        return $this->toPublicProfile($model, $photosByGym[$gymId] ?? []);
    }

    /**
     * @param  Builder<GymProfileModel>  $query
     */
    private function applyBounds(Builder $query, GeoBounds $bounds): void
    {
        $query->whereNotNull('companies_gym_profiles.latitude')
            ->whereNotNull('companies_gym_profiles.longitude')
            ->whereBetween('companies_gym_profiles.latitude', [$bounds->swLat, $bounds->neLat])
            ->whereBetween('companies_gym_profiles.longitude', [$bounds->swLng, $bounds->neLng]);
    }

    /**
     * Расстояние по большому кругу (формула гаверсинуса); LEAST защищает ASIN от погрешности округления > 1.
     *
     * @param  Builder<GymProfileModel>  $query
     */
    private function applyRadius(Builder $query, GeoRadius $radius): void
    {
        $query->whereNotNull('companies_gym_profiles.latitude')
            ->whereNotNull('companies_gym_profiles.longitude')
            ->whereRaw(
                '? * 2 * ASIN(LEAST(1.0, SQRT('
                .'POWER(SIN(RADIANS(CAST(companies_gym_profiles.latitude AS double precision) - CAST(? AS double precision)) / 2), 2)'
                .' + COS(RADIANS(CAST(? AS double precision))) * COS(RADIANS(CAST(companies_gym_profiles.latitude AS double precision)))'
                .' * POWER(SIN(RADIANS(CAST(companies_gym_profiles.longitude AS double precision) - CAST(? AS double precision)) / 2), 2)'
                .'))) <= ?',
                [self::EARTH_RADIUS_KM, $radius->lat, $radius->lat, $radius->lng, $radius->radiusKm],
            );
    }

    /**
     * @return Builder<GymProfileModel>
     */
    private function publishedProfilesQuery(): Builder
    {
        return GymProfileModel::query()
            ->withoutGlobalScopes()
            ->join('gyms', 'gyms.id', '=', 'companies_gym_profiles.gym_id')
            ->where('companies_gym_profiles.moderation_status', ModerationStatus::APPROVED->value)
            ->where('companies_gym_profiles.is_public', true)
            ->select([
                'companies_gym_profiles.gym_id',
                'companies_gym_profiles.company_id',
                'companies_gym_profiles.description',
                'companies_gym_profiles.address',
                'companies_gym_profiles.district',
                'companies_gym_profiles.latitude',
                'companies_gym_profiles.longitude',
                'companies_gym_profiles.phone',
                'companies_gym_profiles.working_hours',
                'companies_gym_profiles.amenities',
                'gyms.public_id as gym_public_id',
                'gyms.name as gym_name',
            ]);
    }

    /**
     * @param  list<int>  $gymIds
     * @return array<int, list<string>>
     */
    private function loadPhotosByGymIds(array $gymIds): array
    {
        if ($gymIds === []) {
            return [];
        }

        $disk = Storage::disk('public');
        $grouped = [];

        $photos = GymPhotoModel::query()
            ->withoutGlobalScopes()
            ->whereIn('gym_id', $gymIds)
            ->orderBy('order')
            ->orderBy('id')
            ->get(['gym_id', 'path']);

        foreach ($photos as $photo) {
            $gymId = (int) $photo->gym_id;
            $grouped[$gymId][] = $disk->url($photo->path);
        }

        return $grouped;
    }

    /**
     * @param  list<string>  $photos
     */
    private function toPublicProfile(GymProfileModel $model, array $photos): PublicGymProfile
    {
        /** @var string $publicId */
        $publicId = $model->getAttribute('gym_public_id');
        /** @var string $name */
        $name = $model->getAttribute('gym_name');

        return new PublicGymProfile(
            publicId: $publicId,
            companyId: (int) $model->company_id,
            name: $name,
            description: $model->description,
            address: $model->address,
            district: $model->district,
            latitude: $model->latitude !== null ? (float) $model->latitude : null,
            longitude: $model->longitude !== null ? (float) $model->longitude : null,
            phone: $model->phone,
            workingHours: $model->working_hours ?? [],
            amenities: $model->amenities ?? [],
            photos: $photos,
        );
    }
}
