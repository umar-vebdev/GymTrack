<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Companies\Infrastructure\Persistence\GymProfileModel;
use DateTimeImmutable;

final class EloquentGymProfileRepository implements GymProfileRepositoryInterface
{
    public function findByGymId(int $gymId): ?GymProfile
    {
        $model = GymProfileModel::query()->where('gym_id', $gymId)->first();

        if ($model === null) {
            return null;
        }

        return new GymProfile(
            gymId: $model->gym_id,
            description: $model->description,
            address: $model->address,
            latitude: $model->latitude !== null ? (float) $model->latitude : null,
            longitude: $model->longitude !== null ? (float) $model->longitude : null,
            phone: $model->phone,
            workingHours: $model->working_hours ?? [],
            amenities: $model->amenities ?? [],
            status: ModerationStatus::from($model->moderation_status),
            rejectionReason: $model->rejection_reason,
            publishedAt: $model->published_at !== null
                ? DateTimeImmutable::createFromInterface($model->published_at)
                : null,
        );
    }

    public function save(GymProfile $profile): void
    {
        $companyId = GymModel::query()->withoutGlobalScopes()->findOrFail($profile->gymId())->company_id;

        GymProfileModel::query()->withoutGlobalScopes()->updateOrCreate(
            ['gym_id' => $profile->gymId()],
            [
                'company_id' => $companyId,
                'description' => $profile->description(),
                'address' => $profile->address(),
                'latitude' => $profile->latitude(),
                'longitude' => $profile->longitude(),
                'phone' => $profile->phone(),
                'working_hours' => $profile->workingHours(),
                'amenities' => $profile->amenities(),
                'moderation_status' => $profile->status()->value,
                'rejection_reason' => $profile->rejectionReason(),
                'published_at' => $profile->publishedAt(),
            ],
        );
    }
}
