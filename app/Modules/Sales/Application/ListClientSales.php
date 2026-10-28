<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Sales\Domain\Exceptions\ClientNotAvailable;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleRepositoryInterface;

final class ListClientSales
{
    public function __construct(
        private readonly SaleRepositoryInterface $sales,
        private readonly ClientLookupInterface $clients
    ) {}

    /**
     * @return list<Sale>
     *
     * @throws ClientNotAvailable
     */
    public function execute(int $companyId, int $clientId): array
    {
        if (!$this->clients->existsInCompany($clientId, $companyId)) {
            throw ClientNotAvailable::withId($clientId);
        }

        return $this->sales->listByClient($companyId, $clientId);
    }
}

