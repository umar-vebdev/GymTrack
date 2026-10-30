<?php

declare(strict_types=1);

namespace App\Modules\Visits\Infrastructure;

use App\Modules\Visits\Domain\Visit;
use App\Modules\Visits\Domain\VisitMethod;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Modules\Visits\Domain\VisitStatus;
use App\Modules\Visits\Infrastructure\Persistence\VisitModel;
use DateTimeImmutable;

final class EloquentVisitRepository implements VisitRepositoryInterface
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
    ): Visit {
        $visitDate = $visitedAt->format('Y-m-d');
        $existingToday = $this->countCompletedForClientInGymOnDate($clientId, $gymId, $visitDate);

        $model = VisitModel::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'gym_id' => $gymId,
            'client_id' => $clientId,
            'membership_id' => $membershipId,
            'method' => $method->value,
            'status' => VisitStatus::Completed->value,
            'visited_at' => $visitedAt->format('Y-m-d H:i:s'),
            'visit_date' => $visitDate,
            'daily_entry_number' => $existingToday + 1,
            'registered_by_user_id' => $registeredByUserId,
            'device' => $device,
            'idempotency_key' => $idempotencyKey,
        ]);

        return $this->mapToDomain($model);
    }

    public function findById(int $id): ?Visit
    {
        $model = VisitModel::withoutGlobalScopes()->find($id);

        return $model ? $this->mapToDomain($model) : null;
    }

    public function findByIdempotencyKey(int $companyId, string $idempotencyKey): ?Visit
    {
        $model = VisitModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $model ? $this->mapToDomain($model) : null;
    }

    public function countCompletedForClientInGymOnDate(int $clientId, int $gymId, string $visitDate): int
    {
        return VisitModel::withoutGlobalScopes()
            ->where('client_id', $clientId)
            ->where('gym_id', $gymId)
            ->where('visit_date', $visitDate)
            ->where('status', VisitStatus::Completed->value)
            ->count();
    }

    public function save(Visit $visit): void
    {
        VisitModel::withoutGlobalScopes()
            ->where('id', $visit->id())
            ->update([
                'status' => $visit->status()->value,
                'cancel_reason' => $visit->cancelReason(),
                'cancelled_by_user_id' => $visit->cancelledByUserId(),
                'cancelled_at' => $visit->cancelledAt()?->format('Y-m-d H:i:s'),
            ]);
    }

    /**
     * @return list<Visit>
     */
    public function listByClient(int $companyId, int $clientId): array
    {
        return array_values(
            VisitModel::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('client_id', $clientId)
                ->orderByDesc('visited_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (VisitModel $m): Visit => $this->mapToDomain($m))
                ->all()
        );
    }

    /**
     * @return list<Visit>
     */
    public function listByCompany(int $companyId): array
    {
        return array_values(
            VisitModel::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->orderByDesc('visited_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (VisitModel $m): Visit => $this->mapToDomain($m))
                ->all()
        );
    }

    private function mapToDomain(VisitModel $model): Visit
    {
        return new Visit(
            id: $model->id,
            companyId: $model->company_id,
            gymId: $model->gym_id,
            clientId: $model->client_id,
            membershipId: $model->membership_id,
            method: VisitMethod::from($model->method),
            status: VisitStatus::from($model->status),
            visitedAt: $model->visited_at->toDateTimeImmutable(),
            visitDate: (string) $model->visit_date,
            registeredByUserId: $model->registered_by_user_id,
            device: $model->device,
            idempotencyKey: $model->idempotency_key,
            cancelReason: $model->cancel_reason,
            cancelledByUserId: $model->cancelled_by_user_id,
            cancelledAt: $model->cancelled_at?->toDateTimeImmutable(),
        );
    }
}
