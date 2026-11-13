<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMember;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;

final class EloquentStaffMemberRepository implements StaffMemberRepositoryInterface
{
    public function findById(int $id): ?StaffMember
    {
        $model = StaffMemberModel::query()->withoutGlobalScopes()->find($id);

        return $model ? $this->mapToDomain($model) : null;
    }

    public function findByUserAndCompany(int $userId, int $companyId): ?StaffMember
    {
        $model = StaffMemberModel::query()
            ->withoutGlobalScopes()
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->first();

        return $model ? $this->mapToDomain($model) : null;
    }

    /**
     * @return list<StaffMember>
     */
    public function listByCompany(int $companyId): array
    {
        return array_values(
            StaffMemberModel::query()
                ->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->get()
                ->map(fn (StaffMemberModel $model): StaffMember => $this->mapToDomain($model))
                ->all()
        );
    }

    public function countByCompanyAndRole(int $companyId, Role $role): int
    {
        return StaffMemberModel::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('role', $role->value)
            ->count();
    }

    public function create(int $userId, int $companyId, Role $role): int
    {
        $model = StaffMemberModel::create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'role' => $role->value,
        ]);

        return $model->id;
    }

    public function save(StaffMember $member): void
    {
        StaffMemberModel::query()
            ->withoutGlobalScopes()
            ->where('id', $member->id())
            ->where('company_id', $member->companyId())
            ->update([
                'role' => $member->role()->value,
            ]);
    }

    public function delete(int $staffId, int $companyId): void
    {
        StaffMemberModel::query()
            ->withoutGlobalScopes()
            ->where('id', $staffId)
            ->where('company_id', $companyId)
            ->delete();
    }

    private function mapToDomain(StaffMemberModel $model): StaffMember
    {
        return new StaffMember(
            id: $model->id,
            userId: $model->user_id,
            companyId: $model->company_id,
            role: Role::from($model->role),
        );
    }
}
