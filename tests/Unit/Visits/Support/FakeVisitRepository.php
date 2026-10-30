<?php

declare(strict_types=1);

namespace Tests\Unit\Visits\Support;

use App\Modules\Visits\Domain\Visit;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use DateTimeImmutable;
use LogicException;

/**
 * Фейковый репозиторий для unit-тестов правил: отдаёт заранее заданное число посещений за день.
 */
final class FakeVisitRepository implements VisitRepositoryInterface
{
    public function __construct(private readonly int $completedToday) {}

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
    ): Visit {
        throw new LogicException('Not used in rule tests.');
    }

    public function findById(int $id): ?Visit
    {
        return null;
    }

    public function findByIdempotencyKey(int $companyId, string $idempotencyKey): ?Visit
    {
        return null;
    }

    public function countCompletedForClientInGymOnDate(int $clientId, int $gymId, string $visitDate): int
    {
        return $this->completedToday;
    }

    public function save(Visit $visit): void {}

    public function listByClient(int $companyId, int $clientId): array
    {
        return [];
    }

    public function listByCompany(int $companyId): array
    {
        return [];
    }
}
