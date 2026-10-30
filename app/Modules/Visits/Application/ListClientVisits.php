<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Visits\Domain\Visit;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use DomainException;

final class ListClientVisits
{
    public function __construct(
        private readonly VisitRepositoryInterface $visits,
        private readonly ClientLookupInterface $clients,
    ) {}

    /**
     * @return list<Visit>
     *
     * @throws DomainException
     */
    public function execute(int $companyId, int $clientId): array
    {
        if (!$this->clients->existsInCompany($clientId, $companyId)) {
            throw new DomainException('Клиент не найден в компании.');
        }

        return $this->visits->listByClient($companyId, $clientId);
    }
}
