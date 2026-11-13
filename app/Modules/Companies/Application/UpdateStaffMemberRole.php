<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Contracts\StaffMemberRoleChanged;
use App\Modules\Companies\Domain\Exceptions\StaffMemberNotFound;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMember;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Identity\Contracts\UserQueryInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Смена роли сотрудника компании с фиксацией в аудит-логе (Бизнес-ТЗ 2, 4.1; Тех. ТЗ 8).
 */
final class UpdateStaffMemberRole
{
    public function __construct(
        private readonly StaffMemberRepositoryInterface $staff,
        private readonly UserQueryInterface $users,
    ) {}

    public function execute(int $companyId, int $actorUserId, int $staffId, Role $newRole): StaffMemberView
    {
        if ($newRole === Role::OWNER) {
            throw new InvalidArgumentException('Нельзя назначить роль владельца через смену роли сотрудника.');
        }

        /** @var array{member: StaffMember, oldRole: Role} $result */
        $result = DB::transaction(function () use ($companyId, $staffId, $newRole): array {
            $member = $this->staff->findById($staffId);
            if ($member === null || $member->companyId() !== $companyId) {
                throw StaffMemberNotFound::withId($staffId);
            }

            if ($member->role() === Role::OWNER) {
                throw new DomainException('Нельзя изменить роль владельца компании.');
            }

            $oldRole = $member->role();
            if ($oldRole === $newRole) {
                return ['member' => $member, 'oldRole' => $oldRole];
            }

            $updated = $member->withRole($newRole);
            $this->staff->save($updated);

            return ['member' => $updated, 'oldRole' => $oldRole];
        });

        $member = $result['member'];
        $oldRole = $result['oldRole'];

        if ($oldRole !== $newRole) {
            Event::dispatch(new StaffMemberRoleChanged(
                staffId: $member->id(),
                companyId: $companyId,
                targetUserId: $member->userId(),
                oldRole: $oldRole->value,
                newRole: $newRole->value,
                actorUserId: $actorUserId,
            ));
        }

        $summary = $this->users->findSummaryById($member->userId());

        return new StaffMemberView(
            id: $member->id(),
            userId: $member->userId(),
            companyId: $member->companyId(),
            role: $member->role(),
            name: $summary?->name,
            phone: $summary?->phone,
            email: $summary?->email,
        );
    }
}

