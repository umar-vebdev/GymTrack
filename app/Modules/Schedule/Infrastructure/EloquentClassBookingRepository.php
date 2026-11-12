<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Infrastructure;

use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\ClassBookingRepositoryInterface;
use App\Modules\Schedule\Domain\ClassBookingStatus;
use App\Modules\Schedule\Infrastructure\Persistence\ClassBookingModel;
use DateTimeImmutable;
use DateTimeZone;

final class EloquentClassBookingRepository implements ClassBookingRepositoryInterface
{
    public function create(
        int $companyId,
        int $gymId,
        int $sessionId,
        int $clientId,
        DateTimeImmutable $bookedAt,
        ?string $idempotencyKey = null,
    ): ClassBooking {
        $utc = new DateTimeZone('UTC');
        $cleanKey = ClassBooking::cleanIdempotencyKey($idempotencyKey);

        $model = ClassBookingModel::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'gym_id' => $gymId,
            'session_id' => $sessionId,
            'client_id' => $clientId,
            'status' => ClassBookingStatus::BOOKED->value,
            'idempotency_key' => $cleanKey,
            'booked_at' => $bookedAt->setTimezone($utc)->format('Y-m-d H:i:s'),
            'cancelled_at' => null,
        ]);

        return $this->toDomain($model->refresh());
    }

    public function save(ClassBooking $booking): void
    {
        $utc = new DateTimeZone('UTC');

        ClassBookingModel::withoutGlobalScopes()
            ->where('company_id', $booking->companyId())
            ->whereKey($booking->id())
            ->update([
                'status' => $booking->status()->value,
                'cancelled_at' => $booking->cancelledAt()?->setTimezone($utc)->format('Y-m-d H:i:s'),
            ]);
    }

    public function findById(int $id): ?ClassBooking
    {
        $model = ClassBookingModel::withoutGlobalScopes()
            ->whereKey($id)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findByIdForUpdate(int $companyId, int $id): ?ClassBooking
    {
        $model = ClassBookingModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findByIdempotencyKey(int $companyId, int $clientId, string $idempotencyKey): ?ClassBooking
    {
        $cleanKey = ClassBooking::cleanIdempotencyKey($idempotencyKey);
        if ($cleanKey === null) {
            return null;
        }

        $model = ClassBookingModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->where('idempotency_key', $cleanKey)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findActiveForSessionAndClient(int $companyId, int $sessionId, int $clientId): ?ClassBooking
    {
        $model = ClassBookingModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('session_id', $sessionId)
            ->where('client_id', $clientId)
            ->where('status', ClassBookingStatus::BOOKED->value)
            ->first();

        return $model !== null ? $this->toDomain($model) : null;
    }

    /**
     * @return list<ClassBooking>
     */
    public function listForSession(int $companyId, int $sessionId): array
    {
        return array_values(
            ClassBookingModel::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('session_id', $sessionId)
                ->orderBy('booked_at')
                ->orderBy('id')
                ->get()
                ->map(fn (ClassBookingModel $model): ClassBooking => $this->toDomain($model))
                ->all(),
        );
    }

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
    ): array {
        if ($clientIds === []) {
            return ['items' => [], 'total' => 0];
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        $query = ClassBookingModel::withoutGlobalScopes()
            ->whereIn('client_id', $clientIds);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $total = (clone $query)->count();

        $models = $query
            ->orderByDesc('booked_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'items' => array_values(
                $models->map(fn (ClassBookingModel $model): ClassBooking => $this->toDomain($model))->all(),
            ),
            'total' => $total,
        ];
    }

    private function toDomain(ClassBookingModel $model): ClassBooking
    {
        $utc = new DateTimeZone('UTC');

        return new ClassBooking(
            id: $model->id,
            publicId: $model->public_id,
            companyId: $model->company_id,
            gymId: $model->gym_id,
            sessionId: $model->session_id,
            clientId: $model->client_id,
            status: ClassBookingStatus::from($model->status),
            idempotencyKey: $model->idempotency_key,
            bookedAt: DateTimeImmutable::createFromInterface($model->booked_at)->setTimezone($utc),
            cancelledAt: $model->cancelled_at !== null
                ? DateTimeImmutable::createFromInterface($model->cancelled_at)->setTimezone($utc)
                : null,
        );
    }
}
