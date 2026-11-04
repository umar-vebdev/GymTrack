<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Contracts\GymProfileApproved;
use App\Modules\Companies\Contracts\GymProfileRejected;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class ModerateGymProfileTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: int, 2: int} owner, companyId, gymId */
    private function createPendingProfile(string $company = 'Corp'): array
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, $company);
        $this->app->make(StaffMemberRepositoryInterface::class)->create($owner->id, $companyId, Role::OWNER);
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, 'Gym', null);

        $profile = GymProfile::draft($gymId);
        $profile->update('Описание', 'ул. Рудаки, 1', 38.5, 68.7, '+992900000000', [], []);
        $profile->submit();
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        return [$owner, $companyId, $gymId];
    }

    private function createPlatformAdmin(): User
    {
        return User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_guest_cannot_moderate(): void
    {
        $this->postJson('/api/v1/platform/gyms/1/moderate', ['decision' => 'approve'])
            ->assertStatus(401);
    }

    public function test_non_platform_admin_cannot_moderate(): void
    {
        [$owner, , $gymId] = $this->createPendingProfile();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gymId}/moderate", ['decision' => 'approve'])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_platform_admin_can_approve_pending_profile(): void
    {
        Event::fake([GymProfileApproved::class]);

        $admin = $this->createPlatformAdmin();
        [, $companyId, $gymId] = $this->createPendingProfile();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gymId}/moderate", [
                'decision' => 'approve',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.moderation_status', 'approved')
            ->assertJsonPath('data.rejection_reason', null);

        $this->assertNotNull($response->json('data.published_at'));

        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gymId,
            'moderation_status' => 'approved',
            'rejection_reason' => null,
        ]);

        Event::assertDispatched(GymProfileApproved::class, function (GymProfileApproved $event) use ($gymId, $companyId, $admin) {
            return $event->gymId === $gymId
                && $event->companyId === $companyId
                && $event->moderatorUserId === $admin->id;
        });
    }

    public function test_platform_admin_can_reject_pending_profile_with_reason(): void
    {
        Event::fake([GymProfileRejected::class]);

        $admin = $this->createPlatformAdmin();
        [, $companyId, $gymId] = $this->createPendingProfile();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gymId}/moderate", [
                'decision' => 'reject',
                'reason' => 'Некорректный номер телефона.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.moderation_status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Некорректный номер телефона.')
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gymId,
            'moderation_status' => 'rejected',
            'rejection_reason' => 'Некорректный номер телефона.',
            'published_at' => null,
        ]);

        Event::assertDispatched(GymProfileRejected::class, function (GymProfileRejected $event) use ($gymId, $companyId, $admin) {
            return $event->gymId === $gymId
                && $event->companyId === $companyId
                && $event->moderatorUserId === $admin->id
                && $event->reason === 'Некорректный номер телефона.';
        });
    }

    public function test_reject_without_reason_returns_validation_error(): void
    {
        $admin = $this->createPlatformAdmin();
        [, , $gymId] = $this->createPendingProfile();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gymId}/moderate", [
                'decision' => 'reject',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_cannot_moderate_draft_profile(): void
    {
        $admin = $this->createPlatformAdmin();

        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, 'Draft Corp');
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, 'Gym', null);

        $profile = GymProfile::draft($gymId);
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gymId}/moderate", [
                'decision' => 'approve',
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');
    }

    public function test_cannot_moderate_already_approved_profile(): void
    {
        $admin = $this->createPlatformAdmin();
        [, , $gymId] = $this->createPendingProfile();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gymId}/moderate", ['decision' => 'approve'])
            ->assertStatus(200);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/platform/gyms/{$gymId}/moderate", ['decision' => 'approve'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');
    }

    public function test_moderating_non_existent_gym_returns_404(): void
    {
        $admin = $this->createPlatformAdmin();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/platform/gyms/999999/moderate', [
                'decision' => 'approve',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }
}
