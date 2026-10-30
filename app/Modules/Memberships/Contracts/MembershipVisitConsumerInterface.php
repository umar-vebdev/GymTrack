<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

use DateTimeImmutable;

/**
 * Contract for consuming a membership visit during a check‑in.
 *
 * The implementation lives in the Memberships module and works with
 * a database transaction (SELECT … FOR UPDATE) to guarantee idempotency
 * and correct race‑condition handling.
 */
interface MembershipVisitConsumerInterface
{
    /**
     * Find a suitable membership for a client at the given time.
     *
     * Returns null when no active membership is available.
     */
    public function findCandidate(int $companyId, int $clientId, DateTimeImmutable $now): ?CheckInMembershipCandidate;

    /**
     * Consume one visit of the given membership.
     *
     * Must throw domain exceptions when the operation cannot be performed.
     */
    public function consumeVisit(int $membershipId, DateTimeImmutable $now): void;

    /**
     * Restore a previously consumed visit (used when a visit is cancelled).
     */
    public function restoreVisit(int $membershipId, DateTimeImmutable $now): void;
}
