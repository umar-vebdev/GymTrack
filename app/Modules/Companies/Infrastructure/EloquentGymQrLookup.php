<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Contracts\GymQrLookupInterface;
use App\Modules\Companies\Contracts\GymQrSummary;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Companies\Infrastructure\Persistence\GymProfileModel;

final class EloquentGymQrLookup implements GymQrLookupInterface
{
    public function findById(int $gymId): ?GymQrSummary
    {
        $model = GymModel::withoutGlobalScopes()->find($gymId);

        if ($model === null) {
            return null;
        }

        if ($model->qr_secret === null || $model->qr_secret === '') {
            $model->qr_secret = bin2hex(random_bytes(32));
            $model->save();
        }

        $profile = GymProfileModel::query()
            ->withoutGlobalScopes()
            ->where('gym_id', $model->id)
            ->first(['latitude', 'longitude']);

        $radius = (int) $model->geo_check_radius_meters;

        return new GymQrSummary(
            id: $model->id,
            companyId: $model->company_id,
            name: $model->name,
            qrSecret: $model->qr_secret,
            timezone: $model->timezone ?: 'Asia/Dushanbe',
            geoCheckEnabled: (bool) $model->geo_check_enabled,
            geoCheckRadiusMeters: $radius > 0 ? $radius : 200,
            latitude: $profile?->latitude !== null ? (float) $profile->latitude : null,
            longitude: $profile?->longitude !== null ? (float) $profile->longitude : null,
        );
    }
}
