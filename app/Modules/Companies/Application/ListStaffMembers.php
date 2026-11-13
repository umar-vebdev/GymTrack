<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\StaffMember;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Identity\Contracts\UserQueryInterface;

/**
 * Список сотрудников компании с краткой информацией о пользователях (Бизнес-ТЗ 2, 4.1).
 */
final class ListStaffMembers
{
    public function __construct(
        private readonly StaffMemberRepositoryInterface $staff,
        private readonly UserQueryInterface $users,
    ) {}

    /**
     * @return list<StaffMemberView>
     */
    public function execute(int $companyId): array
    {
        $members = $this->staff->listByCompany($companyId);
        $userIds = array_map(static fn (StaffMember $member): int => $member->userId(), $members);
        $summaries = $this->users->findSummariesByIds($userIds);

        return array_map(static function (StaffMember $member) use ($summaries): StaffMemberView {
            $summary = $summaries[$member->userId()] ?? null;

            return new StaffMemberView(
                id: $member->id(),
                userId: $member->userId(),
                companyId: $member->companyId(),
                role: $member->role(),
                name: $summary?->name,
                phone: $summary?->phone,
                email: $summary?->email,
            );
        }, $members);
    }
}
