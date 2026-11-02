<?php

declare(strict_types=1);

namespace App\Modules\Clients\Infrastructure;

use App\Models\User;
use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Clients\Contracts\ClientRecipientDto;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Clients\Infrastructure\Persistence\ClientModel;

final class EloquentClientLookup implements ClientLookupInterface
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients
    ) {}

    public function existsInCompany(int $clientId, int $companyId): bool
    {
        $client = $this->clients->findById($clientId);

        return $client !== null && $client->companyId() === $companyId;
    }

    public function findIdByUser(int $userId, int $companyId): ?int
    {
        return $this->clients->findIdByUser($userId, $companyId);
    }

    public function findOrCreateForUser(int $userId, int $companyId): int
    {
        $existingId = $this->clients->findIdByUser($userId, $companyId);
        if ($existingId !== null) {
            return $existingId;
        }

        /** @var User|null $user */
        $user = User::find($userId);
        if ($user === null) {
            return $this->clients->create(
                companyId: $companyId,
                name: 'Клиент',
                phone: '',
                userId: $userId
            );
        }

        if (is_string($user->phone) && $user->phone !== '') {
            $this->clients->linkUserByPhone($userId, $user->phone);
            $linkedId = $this->clients->findIdByUser($userId, $companyId);
            if ($linkedId !== null) {
                return $linkedId;
            }
        }

        return $this->clients->create(
            companyId: $companyId,
            name: is_string($user->name) && $user->name !== '' ? $user->name : 'Клиент',
            phone: is_string($user->phone) ? $user->phone : '',
            userId: $userId
        );
    }

    public function findRecipient(int $clientId): ?ClientRecipientDto
    {
        $client = $this->clients->findById($clientId);
        if ($client === null) {
            return null;
        }

        return new ClientRecipientDto(
            clientId: $client->id(),
            companyId: $client->companyId(),
            userId: $client->userId(),
            name: $client->name(),
            phone: $client->phone()
        );
    }

    /**
     * @return list<int>
     */
    public function findAllClientIdsByUser(int $userId): array
    {
        /** @var list<int> */
        return ClientModel::withoutGlobalScopes()
            ->where('user_id', $userId)
            ->pluck('id')
            ->all();
    }
}
