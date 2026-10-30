<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain;

use DateTimeImmutable;

interface VisitRepositoryInterface
{
    public function create(
        int $companyId,
        int $gymId,
        int $clientId,
        int $membershipId,
        VisitMethod $method,
        DateTimeImmutable $visitedAt,
        ?int $registeredByUserId,
        ?string $device = null,
        ?string $idempotencyKey = null,
    ): Visit;

    public function findById(int $id): ?Visit;

    public function findByIdempotencyKey(int $companyId, string $idempotencyKey): ?Visit;

    public function countCompletedForClientInGymOnDate(int $clientId, int $gymId, string $visitDate): int;

    public function save(Visit $visit): void;

    /**
     * @return list<Visit>
     */
    public function listByClient(int $companyId, int $clientId): array;

    /**
     * @return list<Visit>
     */
    public function listByCompany(int $companyId): array;
}

