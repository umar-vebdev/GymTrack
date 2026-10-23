<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Infrastructure;

use App\Modules\Memberships\Domain\MembershipKind;
use App\Modules\Memberships\Domain\MembershipRules;
use App\Modules\Memberships\Domain\MembershipType;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Domain\StartMode;
use App\Modules\Memberships\Infrastructure\Persistence\MembershipTypeModel;
use App\Shared\Domain\Money;

final class EloquentMembershipTypeRepository implements MembershipTypeRepositoryInterface
{
    public function findById(int $id): ?MembershipType
    {
        $model = MembershipTypeModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function create(int $companyId, string $name, Money $price, MembershipRules $rules): int
    {
        $model = MembershipTypeModel::create([
            'company_id' => $companyId,
            'name' => $name,
            'kind' => $rules->kind->value,
            'start_mode' => $rules->startMode->value,
            'price_minor' => $price->amount(),
            'currency' => $price->currency(),
            'visits_count' => $rules->visitsCount,
            'duration_days' => $rules->durationDays,
            'validity_days' => $rules->validityDays,
            'daily_limit' => $rules->dailyLimit,
            'time_from_minute' => $rules->timeFromMinute,
            'time_to_minute' => $rules->timeToMinute,
            'freeze_max_days' => $rules->freezeMaxDays,
            'freeze_max_times' => $rules->freezeMaxTimes,
            'is_active' => true,
        ]);

        return $model->id;
    }

    private function toEntity(MembershipTypeModel $m): MembershipType
    {
        return new MembershipType(
            id: $m->id,
            companyId: $m->company_id,
            name: $m->name,
            price: new Money($m->price_minor, $m->currency),
            rules: MembershipRules::create(
                kind: MembershipKind::from($m->kind),
                startMode: StartMode::from($m->start_mode),
                visitsCount: $m->visits_count,
                durationDays: $m->duration_days,
                validityDays: $m->validity_days,
                dailyLimit: $m->daily_limit,
                timeFromMinute: $m->time_from_minute,
                timeToMinute: $m->time_to_minute,
                freezeMaxDays: $m->freeze_max_days,
                freezeMaxTimes: $m->freeze_max_times
            ),
            isActive: $m->is_active
        );
    }
}
