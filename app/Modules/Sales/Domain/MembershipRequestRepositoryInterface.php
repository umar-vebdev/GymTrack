<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use DateTimeImmutable;

interface MembershipRequestRepositoryInterface
{
    public function findById(int $id): ?MembershipRequest;

    public function findByIdForUpdate(int $id): ?MembershipRequest;

    public function findByIdempotencyKey(string $idempotencyKey): ?MembershipRequest;

    public function create(
        int $companyId,
        int $gymId,
        int $userId,
        ?int $clientId,
        int $membershipTypeId,
        DateTimeImmutable $expiresAt,
        ?string $idempotencyKey = null
    ): MembershipRequest;

    public function save(MembershipRequest $request): void;

    /**
     * @return list<MembershipRequest>
     */
    public function findPendingExpired(DateTimeImmutable $now, int $limit = 100): array;

    /**
     * @return list<MembershipRequest>
     */
    public function listByCompany(int $companyId, ?MembershipRequestStatus $status = null): array;

    /**
     * @return list<MembershipRequest>
     */
    public function listByUser(int $userId): array;
}
