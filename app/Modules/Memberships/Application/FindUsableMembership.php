<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Memberships\Contracts\MembershipLookupInterface;
use App\Modules\Memberships\Contracts\MembershipSummary;
use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use DateTimeImmutable;

final class FindUsableMembership implements MembershipLookupInterface
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships
    ) {}

    public function findUsableForClient(int $companyId, int $clientId, DateTimeImmutable $at): ?MembershipSummary
    {
        $usable = array_values(array_filter(
            $this->memberships->listOpenByClient($companyId, $clientId),
            static fn (Membership $m): bool => $m->isUsableAt($at)
        ));

        if ($usable === []) {
            return null;
        }

        // Раньше заканчивается — выше; без срока — в конец; при равенстве — более ранняя выдача (меньший id).
        usort($usable, static function (Membership $a, Membership $b): int {
            $aEnd = $a->endsAt()?->getTimestamp() ?? PHP_INT_MAX;
            $bEnd = $b->endsAt()?->getTimestamp() ?? PHP_INT_MAX;

            return [$aEnd, $a->id()] <=> [$bEnd, $b->id()];
        });

        $best = $usable[0];

        return new MembershipSummary(
            $best->id(),
            $best->typeId(),
            $best->status()->value,
            $best->remainingVisits(),
            $best->endsAt()
        );
    }
}
