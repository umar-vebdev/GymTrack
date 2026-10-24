<?php

declare(strict_types=1);

namespace App\Modules\Clients\Infrastructure;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Clients\Domain\ClientRepositoryInterface;

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
}
