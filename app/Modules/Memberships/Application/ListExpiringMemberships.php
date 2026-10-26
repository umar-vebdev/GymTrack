<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Shared\Domain\Clock;

final class ListExpiringMemberships
{
    private const DEFAULT_HORIZON_DAYS = 7;

    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly Clock $clock
    ) {}

    /**
     * @return list<Membership>
     */
    public function execute(int $companyId, int $days = self::DEFAULT_HORIZON_DAYS): array
    {
        $from = $this->clock->now();
        $to = $from->modify(sprintf('+%d days', $days));

        return $this->memberships->listExpiringBetween($companyId, $from, $to);
    }
}

