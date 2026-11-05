<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\Gym;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class SubmitGymProfileTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: int, 2: int} owner, companyId, gymId */
    private function createOwnerWithGym(string $company = 'Corp', bool $complete = true): array
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, $company);
        $this->app->make(StaffMemberRepositoryInterface::class)->create($owner->id, $companyId, Role::OWNER);
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, 'Gym', null);

        $profile = GymProfile::draft($gymId);
        if ($complete) {
            $profile->update('Описание', 'ул. Рудаки, 1', 38.5, 68.7, '+992900000000', [], []);
        }
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        return [$owner, $companyId, $gymId];
    }

    public function test_guest_cannot_submit(): void
    {
        $this->postJson('/api/v1/business/gyms/1/profile/submit')->assertStatus(401);
    }

    public function test_owner_submits_complete_profile(): void
    {
        [$owner, , $gymId] = $this->createOwnerWithGym();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gymId}/profile/submit")
            ->assertStatus(200)
            ->assertJsonPath('data.moderation_status', 'pending');

        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gymId, 'moderation_status' => 'pending']);
    }

    public function test_incomplete_profile_is_rejected(): void
    {
        [$owner, , $gymId] = $this->createOwnerWithGym(complete: false);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gymId}/profile/submit")
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');

        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gymId, 'moderation_status' => 'draft']);
    }

    public function test_repeated_submit_is_rejected(): void
    {
        [$owner, , $gymId] = $this->createOwnerWithGym();
        $this->actingAs($owner, 'sanctum')->postJson("/api/v1/business/gyms/{$gymId}/profile/submit")->assertStatus(200);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gymId}/profile/submit")
            ->assertStatus(400);
    }

    public function test_admin_cannot_submit(): void
    {
        [, $companyId, $gymId] = $this->createOwnerWithGym();
        $admin = User::factory()->create();
        $this->app->make(StaffMemberRepositoryInterface::class)->create($admin->id, $companyId, Role::ADMIN);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gymId}/profile/submit")
            ->assertStatus(403);
    }

    public function test_owner_cannot_submit_profile_of_another_company(): void
    {
        [$owner] = $this->createOwnerWithGym('Corp A');
        [, , $foreignGymId] = $this->createOwnerWithGym('Corp B');

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$foreignGymId}/profile/submit")
            ->assertStatus(404);

        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $foreignGymId, 'moderation_status' => 'draft']);
    }

    public function test_submit_reads_profile_after_locking_gym_and_preserves_newer_publication(): void
    {
        [$owner, , $gymId] = $this->createOwnerWithGym();
        $realGyms = $this->app->make(GymRepositoryInterface::class);
        $realProfiles = $this->app->make(GymProfileRepositoryInterface::class);
        $published = $realProfiles->findByGymId($gymId) ?? $this->fail('Profile not found');
        $published->submit();
        $published->approve(new DateTimeImmutable);
        $published->setPublication(true);
        $realProfiles->save($published);

        $locked = false;
        $gyms = Mockery::mock(GymRepositoryInterface::class);
        $gyms->shouldReceive('findByIdForUpdate')->once()->with($gymId)->andReturnUsing(
            function (int $id) use ($realGyms, &$locked): ?Gym {
                $this->assertGreaterThan(1, DB::transactionLevel());
                $locked = true;

                return $realGyms->findByIdForUpdate($id);
            }
        );
        $profiles = Mockery::mock(GymProfileRepositoryInterface::class);
        $profiles->shouldReceive('findByGymId')->once()->with($gymId)->andReturnUsing(
            function (int $id) use ($realProfiles, &$locked): ?GymProfile {
                $this->assertTrue($locked, 'Profile must be read only after locking the gym row.');

                return $realProfiles->findByGymId($id);
            }
        );
        $this->app->instance(GymRepositoryInterface::class, $gyms);
        $this->app->instance(GymProfileRepositoryInterface::class, $profiles);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gymId}/profile/submit")
            ->assertBadRequest()->assertJsonPath('error.code', 'BAD_REQUEST');

        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gymId, 'moderation_status' => 'approved', 'is_public' => true,
        ]);
    }

    public function test_submit_rolls_back_partial_database_changes_on_save_failure(): void
    {
        [$owner, , $gymId] = $this->createOwnerWithGym();
        $draft = $this->app->make(GymProfileRepositoryInterface::class)->findByGymId($gymId);
        $profiles = Mockery::mock(GymProfileRepositoryInterface::class);
        $profiles->shouldReceive('findByGymId')->once()->with($gymId)->andReturn($draft);
        $profiles->shouldReceive('save')->once()->andReturnUsing(function (GymProfile $changed): void {
            DB::table('companies_gym_profiles')->where('gym_id', $changed->gymId())->update(['moderation_status' => 'pending']);
            throw new RuntimeException('Database failure');
        });
        $this->app->instance(GymProfileRepositoryInterface::class, $profiles);
        config(['app.debug' => false]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gymId}/profile/submit")
            ->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');

        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gymId, 'moderation_status' => 'draft',
        ]);
    }
}
