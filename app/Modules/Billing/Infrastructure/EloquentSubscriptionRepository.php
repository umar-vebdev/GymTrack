<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure;

use App\Modules\Billing\Domain\Subscription;
use App\Modules\Billing\Domain\SubscriptionRepositoryInterface;
use App\Modules\Billing\Domain\SubscriptionStatus;
use App\Modules\Billing\Infrastructure\Persistence\SubscriptionModel;
use DateTimeImmutable;

final class EloquentSubscriptionRepository implements SubscriptionRepositoryInterface
{
    public function findByCompanyId(int $companyId): ?Subscription
    {
        $model = SubscriptionModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findByCompanyIdForUpdate(int $companyId): ?Subscription
    {
        $model = SubscriptionModel::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function createTrial(
        int $companyId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt
    ): Subscription {
        // Validate domain invariants before inserting
        new Subscription(
            id: 0,
            companyId: $companyId,
            planId: null,
            status: SubscriptionStatus::Trial,
            startsAt: $startsAt,
            endsAt: $endsAt
        );

        $model = SubscriptionModel::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'plan_id' => null,
            'status' => SubscriptionStatus::Trial->value,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $this->toEntity($model);
    }

    public function save(Subscription $subscription): void
    {
        SubscriptionModel::withoutGlobalScopes()
            ->whereKey($subscription->id())
            ->update([
                'plan_id' => $subscription->planId(),
                'status' => $subscription->status()->value,
                'starts_at' => $subscription->startsAt(),
                'ends_at' => $subscription->endsAt(),
            ]);
    }

    public function listDueForExpiry(DateTimeImmutable $at): array
    {
        $models = SubscriptionModel::withoutGlobalScopes()
            ->whereIn('status', [SubscriptionStatus::Trial->value, SubscriptionStatus::Active->value])
            ->where('ends_at', '<', $at->format('Y-m-d H:i:s'))
            ->orderBy('id')
            ->get();

        return array_values($models->map(fn (SubscriptionModel $m): Subscription => $this->toEntity($m))->all());
    }

    private function toEntity(SubscriptionModel $m): Subscription
    {
        return new Subscription(
            id: $m->id,
            companyId: $m->company_id,
            planId: $m->plan_id,
            status: SubscriptionStatus::from($m->status),
            startsAt: $m->starts_at->toDateTimeImmutable(),
            endsAt: $m->ends_at->toDateTimeImmutable()
        );
    }
}

