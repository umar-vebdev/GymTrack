<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure;

use App\Modules\Sales\Domain\MembershipRequest;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use App\Modules\Sales\Domain\MembershipRequestStatus;
use App\Modules\Sales\Infrastructure\Persistence\MembershipRequestModel;
use DateTimeImmutable;

final class EloquentMembershipRequestRepository implements MembershipRequestRepositoryInterface
{
    public function findById(int $id): ?MembershipRequest
    {
        $model = MembershipRequestModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIdForUpdate(int $id): ?MembershipRequest
    {
        $model = MembershipRequestModel::lockForUpdate()->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?MembershipRequest
    {
        $model = MembershipRequestModel::where('idempotency_key', $idempotencyKey)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function create(
        int $companyId,
        int $gymId,
        int $userId,
        ?int $clientId,
        int $membershipTypeId,
        DateTimeImmutable $expiresAt,
        ?string $idempotencyKey = null
    ): MembershipRequest {
        $model = MembershipRequestModel::create([
            'company_id' => $companyId,
            'gym_id' => $gymId,
            'user_id' => $userId,
            'client_id' => $clientId,
            'membership_type_id' => $membershipTypeId,
            'status' => MembershipRequestStatus::PENDING,
            'expires_at' => $expiresAt,
            'idempotency_key' => $idempotencyKey,
        ]);

        return $this->toDomain($model);
    }

    public function save(MembershipRequest $request): void
    {
        MembershipRequestModel::where('id', $request->id())->update([
            'client_id' => $request->clientId(),
            'status' => $request->status()->value,
            'paid_at' => $request->paidAt(),
            'sale_id' => $request->saleId(),
            'membership_id' => $request->membershipId(),
        ]);
    }

    /**
     * @return list<MembershipRequest>
     */
    public function findPendingExpired(DateTimeImmutable $now, int $limit = 100): array
    {
        /** @var list<MembershipRequest> $items */
        $items = MembershipRequestModel::query()
            ->where('status', MembershipRequestStatus::PENDING->value)
            ->where('expires_at', '<=', $now)
            ->limit($limit)
            ->get()
            ->map(fn (MembershipRequestModel $m) => $this->toDomain($m))
            ->all();

        return $items;
    }

    /**
     * @return list<MembershipRequest>
     */
    public function listByCompany(int $companyId, ?MembershipRequestStatus $status = null): array
    {
        $query = MembershipRequestModel::query()
            ->where('company_id', $companyId);

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        /** @var list<MembershipRequest> $items */
        $items = $query->orderByDesc('id')
            ->get()
            ->map(fn (MembershipRequestModel $m) => $this->toDomain($m))
            ->all();

        return $items;
    }

    /**
     * @return list<MembershipRequest>
     */
    public function listByUser(int $userId): array
    {
        /** @var list<MembershipRequest> $items */
        $items = MembershipRequestModel::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (MembershipRequestModel $m) => $this->toDomain($m))
            ->all();

        return $items;
    }

    private function toDomain(MembershipRequestModel $m): MembershipRequest
    {
        return new MembershipRequest(
            id: $m->id,
            companyId: $m->company_id,
            gymId: $m->gym_id,
            userId: $m->user_id,
            clientId: $m->client_id,
            membershipTypeId: $m->membership_type_id,
            status: $m->status,
            expiresAt: DateTimeImmutable::createFromInterface($m->expires_at),
            paidAt: $m->paid_at ? DateTimeImmutable::createFromInterface($m->paid_at) : null,
            saleId: $m->sale_id,
            membershipId: $m->membership_id,
            idempotencyKey: $m->idempotency_key,
            createdAt: DateTimeImmutable::createFromInterface($m->created_at)
        );
    }
}
