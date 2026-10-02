<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

interface StaffMemberRepositoryInterface
{
    public function findById(int $id): ?StaffMember;

    public function create(int $userId, int $companyId, Role $role): int;
}
