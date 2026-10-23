<?php

declare(strict_types=1);

namespace App\Modules\Clients\Infrastructure;

use App\Modules\Clients\Domain\Client;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Clients\Infrastructure\Persistence\ClientModel;

final class EloquentClientRepository implements ClientRepositoryInterface
{
    public function findById(int $id): ?Client
    {
        $model = ClientModel::find($id);

        return $model ? new Client(
            id: $model->id,
            companyId: $model->company_id,
            userId: $model->user_id,
            name: $model->name,
            phone: $model->phone
        ) : null;
    }

    public function existsByPhone(int $companyId, string $phone): bool
    {
        return ClientModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('phone', $phone)
            ->exists();
    }

    public function linkUserByPhone(int $userId, string $phone): void
    {
        ClientModel::withoutGlobalScopes()
            ->where('phone', $phone)
            ->whereNull('user_id')
            ->update(['user_id' => $userId]);
    }

    public function create(int $companyId, string $name, string $phone, ?int $userId = null): int
    {
        $model = ClientModel::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'name' => $name,
            'phone' => $phone,
        ]);

        return $model->id;
    }
}
