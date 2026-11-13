<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Audit\Infrastructure\Persistence\AuditEntryModel;
use App\Modules\Companies\Application\AddStaffMember;
use App\Modules\Companies\Application\ListStaffMembers;
use App\Modules\Companies\Application\RegisterCompany;
use App\Modules\Companies\Application\RemoveStaffMember;
use App\Modules\Companies\Application\UpdateStaffMemberRole;
use App\Modules\Companies\Domain\Exceptions\StaffMemberNotFound;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class StaffUseCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adds_lists_updates_role_and_removes_staff_with_audit(): void
    {
        $owner = User::factory()->create(['name' => 'Owner', 'phone' => '+992900000001', 'is_phone_verified' => true]);
        $candidate = User::factory()->create(['name' => 'Admin Candidate', 'phone' => '+992900000002', 'is_phone_verified' => true]);
        $unverified = User::factory()->create(['name' => 'Unverified', 'phone' => '+992900000003', 'is_phone_verified' => false]);

        /** @var RegisterCompany $register */
        $register = $this->app->make(RegisterCompany::class);
        $companyId = $register->execute($owner->id, 'FitCorp', 'Central', null)['company_id'];

        /** @var AddStaffMember $add */
        $add = $this->app->make(AddStaffMember::class);
        /** @var ListStaffMembers $list */
        $list = $this->app->make(ListStaffMembers::class);
        /** @var UpdateStaffMemberRole $updateRole */
        $updateRole = $this->app->make(UpdateStaffMemberRole::class);
        /** @var RemoveStaffMember $remove */
        $remove = $this->app->make(RemoveStaffMember::class);

        // Добавление подтверждённого пользователя как admin
        $added = $add->execute($companyId, $owner->id, '+992900000002', Role::ADMIN);
        expect($added->role)->toBe(Role::ADMIN)
            ->and($added->name)->toBe('Admin Candidate')
            ->and($added->phone)->toBe('+992900000002');

        // Запрет неподтверждённого и дубликата и роли owner
        expect(fn () => $add->execute($companyId, $owner->id, $unverified->phone ?? '', Role::ADMIN))
            ->toThrow(DomainException::class);
        expect(fn () => $add->execute($companyId, $owner->id, '+992900000002', Role::TRAINER))
            ->toThrow(DomainException::class);
        expect(fn () => $add->execute($companyId, $owner->id, '+992900000002', Role::OWNER))
            ->toThrow(InvalidArgumentException::class);

        // Список сотрудников
        $all = $list->execute($companyId);
        expect($all)->toHaveCount(2);

        // Смена роли на trainer
        $changed = $updateRole->execute($companyId, $owner->id, $added->id, Role::TRAINER);
        expect($changed->role)->toBe(Role::TRAINER);

        // Защита единственного владельца
        /** @var StaffMemberRepositoryInterface $staffRepo */
        $staffRepo = $this->app->make(StaffMemberRepositoryInterface::class);
        $ownerStaff = $staffRepo->findByUserAndCompany($owner->id, $companyId);
        expect($ownerStaff)->not->toBeNull();

        expect(fn () => $updateRole->execute($companyId, $owner->id, $ownerStaff?->id() ?? 0, Role::ADMIN))
            ->toThrow(DomainException::class);
        expect(fn () => $remove->execute($companyId, $owner->id, $ownerStaff?->id() ?? 0))
            ->toThrow(DomainException::class);

        // Удаление сотрудника
        $remove->execute($companyId, $owner->id, $added->id);
        expect($list->execute($companyId))->toHaveCount(1);

        // Проверка записей в аудит-логе
        $actions = AuditEntryModel::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->pluck('action')
            ->all();

        expect($actions)->toContain('STAFF_MEMBER_ADDED', 'STAFF_MEMBER_ROLE_CHANGED', 'STAFF_MEMBER_REMOVED');
    }

    public function test_it_isolates_staff_across_companies(): void
    {
        $ownerA = User::factory()->create(['phone' => '+992900111001', 'is_phone_verified' => true]);
        $ownerB = User::factory()->create(['phone' => '+992900111002', 'is_phone_verified' => true]);
        $staffUserB = User::factory()->create(['phone' => '+992900111003', 'is_phone_verified' => true]);

        /** @var RegisterCompany $register */
        $register = $this->app->make(RegisterCompany::class);
        $companyA = $register->execute($ownerA->id, 'A', 'Gym A', null)['company_id'];
        $companyB = $register->execute($ownerB->id, 'B', 'Gym B', null)['company_id'];

        /** @var AddStaffMember $add */
        $add = $this->app->make(AddStaffMember::class);
        $staffB = $add->execute($companyB, $ownerB->id, '+992900111003', Role::ADMIN);

        /** @var UpdateStaffMemberRole $updateRole */
        $updateRole = $this->app->make(UpdateStaffMemberRole::class);
        /** @var RemoveStaffMember $remove */
        $remove = $this->app->make(RemoveStaffMember::class);

        expect(fn () => $updateRole->execute($companyA, $ownerA->id, $staffB->id, Role::TRAINER))
            ->toThrow(StaffMemberNotFound::class);
        expect(fn () => $remove->execute($companyA, $ownerA->id, $staffB->id))
            ->toThrow(StaffMemberNotFound::class);
    }
}

