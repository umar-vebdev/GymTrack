<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Memberships\Domain\Exceptions\ClientNotAvailable;
use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;

final class ListClientMemberships
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly ClientLookupInterface $clients
    ) {}

    /**
     * @return list<Membership>
     *
     * @throws ClientNotAvailable
     */
    public function execute(int $companyId, int $clientId): array
    {
        if (!$this->clients->existsInCompany($clientId, $companyId)) {
            throw ClientNotAvailable::withId($clientId);
        }

        return $this->memberships->listByClient($companyId, $clientId);
    }
}
