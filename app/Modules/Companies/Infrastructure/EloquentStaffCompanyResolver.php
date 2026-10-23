<?php

declare(strict_types=1);

namespace App\Modules\Companies\Infrastructure;

use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
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
}
