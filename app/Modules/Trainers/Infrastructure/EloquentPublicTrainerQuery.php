<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Infrastructure;

use App\Modules\Trainers\Contracts\PublicTrainerQueryInterface;
use App\Modules\Trainers\Contracts\PublicTrainerSummary;
use App\Modules\Trainers\Infrastructure\Persistence\TrainerModel;

final class EloquentPublicTrainerQuery implements PublicTrainerQueryInterface
{
    public function findByIds(array $trainerIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $trainerIds,
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        /** @var array<int, PublicTrainerSummary> $result */
        $result = [];

        $models = TrainerModel::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->get(['id', 'public_id', 'name', 'bio']);

        foreach ($models as $model) {
            $result[(int) $model->id] = new PublicTrainerSummary(
                publicId: $model->public_id,
                name: $model->name,
                bio: $model->bio,
            );
        }

        return $result;
    }
}
