<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Contracts\StaffMemberRemoved;
use App\Modules\Companies\Domain\Exceptions\StaffMemberNotFound;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMember;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Удаление сотрудника из компании с защитой единственного владельца и аудит-логом (Тех. ТЗ 8).
 */
final class RemoveStaffMember
{
    public function __construct(
        private readonly StaffMemberRepositoryInterface $staff,
    ) {}

    public function execute(int $companyId, int $actorUserId, int $staffId): void
    {
        /** @var StaffMember $member */
        $member = DB::transaction(function () use ($companyId, $staffId): StaffMember {
            $member = $this->staff->findById($staffId);
            if ($member === null || $member->companyId() !== $companyId) {
                throw StaffMemberNotFound::withId($staffId);
            }

            if ($member->role() === Role::OWNER && $this->staff->countByCompanyAndRole($companyId, Role::OWNER) <= 1) {
                throw new DomainException('Нельзя удалить единственного владельца компании.');
            }

            $this->staff->delete($staffId, $companyId);

            return $member;
        });

        Event::dispatch(new StaffMemberRemoved(
            staffId: $member->id(),
            companyId: $companyId,
            targetUserId: $member->userId(),
            role: $member->role()->value,
            actorUserId: $actorUserId,
        ));
    }
}

