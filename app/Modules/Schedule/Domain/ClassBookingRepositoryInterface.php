<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain;

use DateTimeImmutable;

interface ClassBookingRepositoryInterface
{
    public function create(
        int $companyId,
        int $gymId,
        int $sessionId,
        int $clientId,
        DateTimeImmutable $bookedAt,
        ?string $idempotencyKey = null,
    ): ClassBooking;

    public function save(ClassBooking $booking): void;

    public function findById(int $id): ?ClassBooking;

    public function findByIdForUpdate(int $companyId, int $id): ?ClassBooking;

    public function findByIdempotencyKey(int $companyId, int $clientId, string $idempotencyKey): ?ClassBooking;

    public function findActiveForSessionAndClient(int $companyId, int $sessionId, int $clientId): ?ClassBooking;

    /**
     * @return list<ClassBooking>
     */
    public function listForSession(int $companyId, int $sessionId): array;

    /**
     * @param  list<int>  $clientIds
     * @return array{items: list<ClassBooking>, total: int}
     */
    public function listForClient(
        array $clientIds,
        ?int $companyId = null,
        ?ClassBookingStatus $status = null,
        int $page = 1,
        int $perPage = 20,
    ): array;
}
