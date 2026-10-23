<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Memberships\Domain\MembershipType;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;

final class ListMembershipTypes
{
    public function __construct(
        private readonly MembershipTypeRepositoryInterface $types
    ) {}

    /**
     * @return list<MembershipType>
     */
    public function execute(int $companyId, ?bool $onlyActive = null): array
    {
        return $this->types->listByCompany($companyId, $onlyActive);
    }
}
