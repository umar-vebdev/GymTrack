<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Shared\Domain\Clock;

final class ExpireDueMemberships
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly Clock $clock
    ) {}

    /**
     * Завершает просроченные абонементы указанной компании (ТЗ 9.4).
     * Операция идемпотентна: повторный запуск возвращает 0 и не меняет уже завершённые записи.
     */
    public function execute(int $companyId): int
    {
        $now = $this->clock->now();
        $due = $this->memberships->listDueForExpiry($companyId, $now);

        $expiredCount = 0;

        foreach ($due as $membership) {
            $membership->expireIfDue($now);

            if ($membership->status() === MembershipStatus::Expired) {
                $this->memberships->save($membership);
                $expiredCount++;
            }
        }

        return $expiredCount;
    }

    /**
     * @return list<int>
     */
    public function companyIdsWithDueMemberships(): array
    {
        return $this->memberships->listCompanyIdsWithDueMemberships($this->clock->now());
    }
}
