<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StaffCompanyResolverTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(): int
    {
        $owner = User::factory()->create();

        return $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, 'Corp');
    }

    public function test_back_office_roles_resolve_company_but_trainer_does_not(): void
    {
        $companyId = $this->createCompany();
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $trainer = User::factory()->create();
        $staff->create($owner->id, $companyId, Role::OWNER);
        $staff->create($admin->id, $companyId, Role::ADMIN);
        $staff->create($trainer->id, $companyId, Role::TRAINER);

        $resolver = $this->app->make(StaffCompanyResolverInterface::class);

        $this->assertSame($companyId, $resolver->companyIdForUser($owner->id));
        $this->assertSame($companyId, $resolver->companyIdForUser($admin->id));
        $this->assertNull($resolver->companyIdForUser($trainer->id));
    }

    public function test_only_trainer_role_resolves_trainer_company(): void
    {
        $companyId = $this->createCompany();
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);

        $admin = User::factory()->create();
        $trainer = User::factory()->create();
        $stranger = User::factory()->create();
        $staff->create($admin->id, $companyId, Role::ADMIN);
        $staff->create($trainer->id, $companyId, Role::TRAINER);

        $resolver = $this->app->make(StaffCompanyResolverInterface::class);

        $this->assertSame($companyId, $resolver->companyIdForTrainer($trainer->id));
        $this->assertNull($resolver->companyIdForTrainer($admin->id));
        $this->assertNull($resolver->companyIdForTrainer($stranger->id));
    }

    public function test_trainer_is_forbidden_on_business_endpoints_while_admin_is_allowed(): void
    {
        $companyId = $this->createCompany();
        $staff = $this->app->make(StaffMemberRepositoryInterface::class);

        $admin = User::factory()->create();
        $trainer = User::factory()->create();
        $staff->create($admin->id, $companyId, Role::ADMIN);
        $staff->create($trainer->id, $companyId, Role::TRAINER);

        $this->actingAs($trainer, 'sanctum')->getJson('/api/v1/business/products')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/business/products')
            ->assertOk();
    }
}
