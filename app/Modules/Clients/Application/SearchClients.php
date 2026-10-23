<?php

declare(strict_types=1);

namespace App\Modules\Clients\Application;

use App\Modules\Clients\Domain\Client;
use App\Modules\Clients\Domain\ClientRepositoryInterface;

final class SearchClients
{
    public function __construct(
        private readonly ClientRepositoryInterface $clients
    ) {}

    /**
     * @return array{items: list<Client>, total: int}
     */
    public function execute(int $companyId, ?string $search, int $page, int $perPage): array
    {
        $search = $search !== null ? trim($search) : null;

        return $this->clients->search(
            $companyId,
            $search === '' ? null : $search,
            $page,
            $perPage
        );
    }
}
