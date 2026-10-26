<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Memberships\Domain\Exceptions\MembershipFreezeNotAllowed;
use App\Modules\Memberships\Domain\Exceptions\MembershipNotFound;
use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;

final class FreezeMembership
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships,
        private readonly Clock $clock
    ) {}

    /**
     * @throws MembershipNotFound
     * @throws MembershipFreezeNotAllowed
     */
    public function execute(int $companyId, int $membershipId): Membership
    {
        return DB::transaction(function () use ($companyId, $membershipId): Membership {
            $membership = $this->memberships->findByIdForUpdate($membershipId);

            if ($membership === null || $membership->companyId() !== $companyId) {
                throw MembershipNotFound::withId($membershipId);
            }

            $membership->freeze($this->clock->now());
            $this->memberships->save($membership);

            return $membership;
        });
    }
}
