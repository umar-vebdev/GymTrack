<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Contracts\StaffMemberAdded;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Identity\Contracts\UserQueryInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

/**
 * Добавление сотрудника в компанию по подтверждённому номеру телефона (Бизнес-ТЗ 2, 4.1; Тех. ТЗ 8).
 */
final class AddStaffMember
{
    public function __construct(
        private readonly StaffMemberRepositoryInterface $staff,
        private readonly UserQueryInterface $users,
    ) {}

    public function execute(int $companyId, int $actorUserId, string $phone, Role $role): StaffMemberView
    {
        if ($role === Role::OWNER) {
            throw new InvalidArgumentException('Нельзя назначить роль владельца при добавлении сотрудника.');
        }

        $targetUserId = $this->users->findVerifiedUserIdByPhone($phone);
        if ($targetUserId === null) {
            throw new DomainException('Пользователь с таким подтверждённым номером телефона не найден.');
        }

        $staffId = DB::transaction(function () use ($companyId, $targetUserId, $role): int {
            if ($this->staff->findByUserAndCompany($targetUserId, $companyId) !== null) {
                throw new DomainException('Пользователь уже является сотрудником этой компании.');
            }

            return $this->staff->create($targetUserId, $companyId, $role);
        });

        Event::dispatch(new StaffMemberAdded(
            staffId: $staffId,
            companyId: $companyId,
            targetUserId: $targetUserId,
            role: $role->value,
            actorUserId: $actorUserId,
        ));

        $summary = $this->users->findSummaryById($targetUserId);

        return new StaffMemberView(
            id: $staffId,
            userId: $targetUserId,
            companyId: $companyId,
            role: $role,
            name: $summary?->name,
            phone: $summary?->phone,
            email: $summary?->email,
        );
    }
}
