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

    public function search(int $companyId, ?string $search, int $page, int $perPage): array
    {
        $query = ClientModel::withoutGlobalScopes()->where('company_id', $companyId);

        if ($search !== null) {
            $like = '%'.addcslashes($search, '\\%_').'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'ilike', $like)->orWhere('phone', 'like', $like);
            });
        }

        $total = $query->count();

        $items = $query->orderBy('name')->orderBy('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(static fn (ClientModel $m): Client => new Client(
                id: $m->id,
                companyId: $m->company_id,
                userId: $m->user_id,
                name: $m->name,
                phone: $m->phone
            ))
            ->all();

        return ['items' => array_values($items), 'total' => $total];
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
