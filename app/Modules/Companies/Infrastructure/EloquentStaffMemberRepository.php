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
        $model = StaffMemberModel::find($id);

        return $model ? new StaffMember(
            id: $model->id,
            userId: $model->user_id,
            companyId: $model->company_id,
            role: Role::from($model->role)
        ) : null;
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
}
