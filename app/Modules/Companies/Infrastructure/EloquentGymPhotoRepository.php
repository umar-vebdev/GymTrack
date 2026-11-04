<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Domain\GymPhoto;
use App\Modules\Companies\Domain\GymPhotoRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\GymModel;
use App\Modules\Companies\Infrastructure\Persistence\GymPhotoModel;
use DateTimeImmutable;

final class EloquentGymPhotoRepository implements GymPhotoRepositoryInterface
{
    public function create(int $companyId, int $gymId, string $path, int $order): GymPhoto
    {
        GymModel::query()->where('company_id', $companyId)->findOrFail($gymId);

        $model = GymPhotoModel::query()->create([
            'company_id' => $companyId,
            'gym_id' => $gymId,
            'path' => $path,
            'order' => $order,
        ]);

        return $this->toDomain($model);
    }

    public function listForGym(int $companyId, int $gymId): array
    {
        return array_values(GymPhotoModel::query()
            ->where('company_id', $companyId)
            ->where('gym_id', $gymId)
            ->orderBy('order')->orderBy('id')
            ->get()
            ->map(fn (GymPhotoModel $model): GymPhoto => $this->toDomain($model))
            ->all());
    }

    public function delete(int $companyId, int $gymId, int $photoId): bool
    {
        return GymPhotoModel::query()
            ->where('company_id', $companyId)
            ->where('gym_id', $gymId)
            ->whereKey($photoId)
            ->delete() === 1;
    }

    private function toDomain(GymPhotoModel $model): GymPhoto
    {
        return new GymPhoto(
            $model->id,
            $model->company_id,
            $model->gym_id,
            $model->path,
            $model->order,
            DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
