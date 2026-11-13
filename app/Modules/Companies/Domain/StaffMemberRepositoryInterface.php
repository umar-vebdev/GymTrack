<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

interface StaffMemberRepositoryInterface
{
    public function findById(int $id): ?StaffMember;

    public function findByUserAndCompany(int $userId, int $companyId): ?StaffMember;

    /**
     * @return list<StaffMember>
     */
    public function listByCompany(int $companyId): array;

    public function countByCompanyAndRole(int $companyId, Role $role): int;

    public function create(int $userId, int $companyId, Role $role): int;

    public function save(StaffMember $member): void;

    public function delete(int $staffId, int $companyId): void;
}
