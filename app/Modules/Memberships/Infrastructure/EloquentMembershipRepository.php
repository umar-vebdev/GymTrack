<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Infrastructure;

use App\Modules\Memberships\Domain\Membership;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipStatus;
use App\Modules\Memberships\Domain\MembershipTypeNotFound;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Infrastructure\Persistence\MembershipModel;
use DateTimeImmutable;

final class EloquentMembershipRepository implements MembershipRepositoryInterface
{
    public function __construct(
        private readonly MembershipTypeRepositoryInterface $types
    ) {}

    public function findById(int $id): ?Membership
    {
        $model = MembershipModel::withoutGlobalScopes()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByIdForUpdate(int $id): ?Membership
    {
        $model = MembershipModel::withoutGlobalScopes()->whereKey($id)->lockForUpdate()->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function listOpenByClient(int $companyId, int $clientId): array
    {
        $models = MembershipModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->whereIn('status', [MembershipStatus::Active->value, MembershipStatus::Pending->value])
            ->orderBy('id')
            ->get();

        return array_values($models->map(fn (MembershipModel $m): Membership => $this->toEntity($m))->all());
    }

    public function create(
        int $companyId,
        int $clientId,
        int $typeId,
        MembershipStatus $status,
        ?int $remainingVisits,
        ?DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt
    ): int {
        $model = MembershipModel::create([
            'company_id' => $companyId,
            'client_id' => $clientId,
            'type_id' => $typeId,
            'status' => $status->value,
            'remaining_visits' => $remainingVisits,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $model->id;
    }

    public function save(Membership $membership): void
    {
        MembershipModel::withoutGlobalScopes()
            ->whereKey($membership->id())
            ->update([
                'status' => $membership->status()->value,
                'remaining_visits' => $membership->remainingVisits(),
                'starts_at' => $membership->startsAt(),
                'ends_at' => $membership->endsAt(),
                'freeze_days_used' => $membership->freezeDaysUsed(),
                'freeze_times_used' => $membership->freezeTimesUsed(),
                'frozen_at' => $membership->frozenAt(),
                'cancellation_reason' => $membership->cancellationReason(),
            ]);
    }

    private function toEntity(MembershipModel $m): Membership
    {
        $type = $this->types->findById($m->type_id)
            ?? throw MembershipTypeNotFound::withId($m->type_id);

        return new Membership(
            id: $m->id,
            companyId: $m->company_id,
            clientId: $m->client_id,
            typeId: $m->type_id,
            rules: $type->rules(),
            status: MembershipStatus::from($m->status),
            remainingVisits: $m->remaining_visits,
            startsAt: $m->starts_at?->toDateTimeImmutable(),
            endsAt: $m->ends_at?->toDateTimeImmutable(),
            freezeDaysUsed: $m->freeze_days_used,
            freezeTimesUsed: $m->freeze_times_used,
            frozenAt: $m->frozen_at?->toDateTimeImmutable(),
            cancellationReason: $m->cancellation_reason
        );
    }
}
