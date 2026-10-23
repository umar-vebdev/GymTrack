<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Infrastructure\Persistence\StaffMemberModel;

final class EloquentStaffCompanyResolver implements StaffCompanyResolverInterface
{
    public function companyIdForUser(int $userId): ?int
    {
        $model = StaffMemberModel::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->first();

        return $model?->company_id;
    }

    public function isOwner(int $userId, int $companyId): bool
    {
        return StaffMemberModel::query()
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('role', Role::OWNER->value)
            ->exists();
    }
}
