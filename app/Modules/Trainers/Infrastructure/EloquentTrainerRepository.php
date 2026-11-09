<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Infrastructure;

use App\Modules\Trainers\Domain\Trainer;
use App\Modules\Trainers\Domain\TrainerRepositoryInterface;
use App\Modules\Trainers\Infrastructure\Persistence\TrainerModel;

final class EloquentTrainerRepository implements TrainerRepositoryInterface
{
    public function create(int $companyId, int $gymId, string $name, ?string $bio = null, ?int $userId = null): Trainer
    {
        $model = TrainerModel::query()->create([
            'company_id' => $companyId,
            'gym_id' => $gymId,
            'user_id' => $userId,
            'name' => Trainer::cleanName($name),
            'bio' => Trainer::cleanBio($bio),
            'is_active' => true,
        ]);

        return $this->toDomain($model->refresh());
    }

    public function save(Trainer $trainer): void
    {
        TrainerModel::query()
            ->where('company_id', $trainer->companyId())
            ->whereKey($trainer->id())
            ->update([
                'name' => $trainer->name(),
                'bio' => $trainer->bio(),
                'is_active' => $trainer->isActive(),
            ]);
    }

    public function findById(int $companyId, int $id): ?Trainer
    {
        $model = TrainerModel::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function listForGym(int $companyId, int $gymId): array
    {
        return array_values(TrainerModel::query()
            ->where('company_id', $companyId)
            ->where('gym_id', $gymId)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->map(fn (TrainerModel $model): Trainer => $this->toDomain($model))
            ->all());
    }

    private function toDomain(TrainerModel $model): Trainer
    {
        return new Trainer(
            id: $model->id,
            publicId: $model->public_id,
            companyId: $model->company_id,
            gymId: $model->gym_id,
            userId: $model->user_id,
            name: $model->name,
            bio: $model->bio,
            isActive: $model->is_active,
        );
    }
}
