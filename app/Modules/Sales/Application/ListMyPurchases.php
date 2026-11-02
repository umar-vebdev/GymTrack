<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleRepositoryInterface;

final class ListMyPurchases
{
    public function __construct(
        private readonly SaleRepositoryInterface $sales,
        private readonly ClientLookupInterface $clients
    ) {}

    /**
     * @return array{items: list<Sale>, total: int}
     */
    public function execute(
        int $userId,
        ?int $companyId = null,
        ?string $status = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        $clientIds = $this->clients->findAllClientIdsByUser($userId);
        if ($clientIds === []) {
            return [
                'items' => [],
                'total' => 0,
            ];
        }

        return $this->sales->listForClients(
            clientIds: $clientIds,
            companyId: $companyId,
            status: $status,
            page: $page,
            perPage: $perPage
        );
    }
}

