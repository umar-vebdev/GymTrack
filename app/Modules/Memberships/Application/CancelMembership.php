<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Application;

use App\Modules\Memberships\Contracts\MembershipCancellerInterface;
use App\Modules\Memberships\Domain\Events\MembershipCancelled;
use App\Modules\Memberships\Domain\Exceptions\MembershipAlreadyCancelled;
use App\Modules\Memberships\Domain\Exceptions\MembershipNotFound;
use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class CancelMembership implements MembershipCancellerInterface
{
    public function __construct(
        private readonly MembershipRepositoryInterface $memberships
    ) {}

    public function cancel(
        int $companyId,
        int $membershipId,
        string $reason,
        ?int $cancelledByUserId = null
    ): void {
        try {
            $this->execute($companyId, $membershipId, $reason, $cancelledByUserId);
        } catch (MembershipAlreadyCancelled) {
            // Если абонемент уже был отменён вручную ранее, повторная отмена при возврате чека идемпотентна.
        }
    }

    /**
     * @throws MembershipNotFound
     * @throws MembershipAlreadyCancelled
     */
    public function execute(
        int $companyId,
        int $membershipId,
        string $reason,
        ?int $cancelledByUserId = null
    ): Membership {
        $membership = DB::transaction(function () use ($companyId, $membershipId, $reason): Membership {
            $membership = $this->memberships->findByIdForUpdate($membershipId);

            if ($membership === null || $membership->companyId() !== $companyId) {
                throw MembershipNotFound::withId($membershipId);
            }

            $membership->cancel($reason);
            $this->memberships->save($membership);

            return $membership;
        });

        Event::dispatch(new MembershipCancelled(
            membershipId: $membership->id(),
            companyId: $companyId,
            clientId: $membership->clientId(),
            reason: $membership->cancellationReason() ?? $reason,
            cancelledByUserId: $cancelledByUserId
        ));

        return $membership;
    }
}
