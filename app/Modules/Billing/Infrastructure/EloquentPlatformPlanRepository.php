<?php

declare(strict_types=1);

namespace App\Modules\Billing\Infrastructure;

use App\Modules\Billing\Domain\BillingPeriod;
use App\Modules\Billing\Domain\PlatformPlan;
use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;
use App\Modules\Billing\Infrastructure\Persistence\PlatformPlanModel;
use App\Shared\Domain\Money;

final class EloquentPlatformPlanRepository implements PlatformPlanRepositoryInterface
{
    public function findById(int $id): ?PlatformPlan
    {
        $model = PlatformPlanModel::query()->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function create(
        string $name,
        BillingPeriod $period,
        Money $price,
        int $maxGyms,
        int $maxStaff,
        bool $isActive = true
    ): int {
        // Validate domain invariants before inserting
        new PlatformPlan(
            id: 0,
            name: $name,
            period: $period,
            price: $price,
            maxGyms: $maxGyms,
            maxStaff: $maxStaff,
            isActive: $isActive
        );

        $model = PlatformPlanModel::create([
            'name' => trim($name),
            'period' => $period->value,
            'price_minor' => $price->amount(),
            'currency' => $price->currency(),
            'max_gyms' => $maxGyms,
            'max_staff' => $maxStaff,
            'is_active' => $isActive,
        ]);

        return $model->id;
    }

    public function listAll(bool $onlyActive = false): array
    {
        $query = PlatformPlanModel::query()->orderBy('id');

        if ($onlyActive) {
            $query->where('is_active', true);
        }

        return array_values($query->get()->map(fn (PlatformPlanModel $m): PlatformPlan => $this->toEntity($m))->all());
    }

    public function save(PlatformPlan $plan): void
    {
        PlatformPlanModel::query()
            ->whereKey($plan->id())
            ->update([
                'is_active' => $plan->isActive(),
            ]);
    }

    private function toEntity(PlatformPlanModel $m): PlatformPlan
    {
        return new PlatformPlan(
            id: $m->id,
            name: $m->name,
            period: BillingPeriod::from($m->period),
            price: new Money($m->price_minor, $m->currency),
            maxGyms: $m->max_gyms,
            maxStaff: $m->max_staff,
            isActive: $m->is_active
        );
    }
}
