<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Infrastructure;

use App\Modules\Trainers\Contracts\TrainerLookupInterface;
use App\Modules\Trainers\Infrastructure\Persistence\TrainerModel;

final class EloquentTrainerLookup implements TrainerLookupInterface
{
    public function existsActiveInGym(int $trainerId, int $companyId, int $gymId): bool
    {
        return TrainerModel::query()
            ->withoutGlobalScopes()
            ->whereKey($trainerId)
            ->where('company_id', $companyId)
            ->where('gym_id', $gymId)
            ->where('is_active', true)
            ->exists();
    }
}
