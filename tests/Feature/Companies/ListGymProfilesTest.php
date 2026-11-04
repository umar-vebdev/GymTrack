<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ListGymProfilesTest extends TestCase
{
    use RefreshDatabase;

    private function createGymWithProfile(ModerationStatus $status, string $company = 'Corp'): int
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, $company);
        $this->app->make(StaffMemberRepositoryInterface::class)->create($owner->id, $companyId, Role::OWNER);
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, 'Gym', null);

        $profile = GymProfile::draft($gymId);
        if ($status !== ModerationStatus::DRAFT) {
            $profile->update('Описание', 'ул. Ленина, 10', 38.5, 68.7, '+992900000000', [], []);
            $profile->submit();
            if ($status === ModerationStatus::APPROVED) {
                $profile->approve(new DateTimeImmutable('2026-11-04 10:00:00'));
            } elseif ($status === ModerationStatus::REJECTED) {
                $profile->reject('Причина отклонения');
            }
        }
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        return $gymId;
    }

    private function createPlatformAdmin(): User
    {
        return User::factory()->create(['is_platform_admin' => true]);
    }

    public function test_guest_cannot_list_profiles(): void
    {
        $this->getJson('/api/v1/platform/gym-profiles')
            ->assertStatus(401);
    }

    public function test_non_platform_admin_cannot_list_profiles(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/platform/gym-profiles')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_platform_admin_lists_pending_profiles_by_default(): void
    {
        $admin = $this->createPlatformAdmin();

        $gymPending1 = $this->createGymWithProfile(ModerationStatus::PENDING, 'Corp A');
        $gymPending2 = $this->createGymWithProfile(ModerationStatus::PENDING, 'Corp B');
        $this->createGymWithProfile(ModerationStatus::DRAFT, 'Corp C');
        $this->createGymWithProfile(ModerationStatus::APPROVED, 'Corp D');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/platform/gym-profiles');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonCount(2, 'data');

        $gymIds = array_column($response->json('data'), 'gym_id');
        $this->assertContains($gymPending1, $gymIds);
        $this->assertContains($gymPending2, $gymIds);

        foreach ($response->json('data') as $item) {
            $this->assertSame('pending', $item['moderation_status']);
        }
    }

    public function test_platform_admin_can_filter_by_status(): void
    {
        $admin = $this->createPlatformAdmin();

        $this->createGymWithProfile(ModerationStatus::PENDING, 'Corp A');
        $gymApproved = $this->createGymWithProfile(ModerationStatus::APPROVED, 'Corp B');
        $gymRejected = $this->createGymWithProfile(ModerationStatus::REJECTED, 'Corp C');

        $responseApproved = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/platform/gym-profiles?status=approved');

        $responseApproved->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.gym_id', $gymApproved)
            ->assertJsonPath('data.0.moderation_status', 'approved');

        $responseRejected = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/platform/gym-profiles?status=rejected');

        $responseRejected->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.gym_id', $gymRejected)
            ->assertJsonPath('data.0.moderation_status', 'rejected')
            ->assertJsonPath('data.0.rejection_reason', 'Причина отклонения');
    }

    public function test_platform_admin_can_paginate_profiles(): void
    {
        $admin = $this->createPlatformAdmin();

        $gym1 = $this->createGymWithProfile(ModerationStatus::PENDING, 'Corp 1');
        $gym2 = $this->createGymWithProfile(ModerationStatus::PENDING, 'Corp 2');
        $gym3 = $this->createGymWithProfile(ModerationStatus::PENDING, 'Corp 3');

        $page1 = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/platform/gym-profiles?per_page=2&page=1');

        $page1->assertStatus(200)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data');

        $page2 = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/platform/gym-profiles?per_page=2&page=2');

        $page2->assertStatus(200)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(1, 'data');

        $allReturnedGymIds = [
            $page1->json('data.0.gym_id'),
            $page1->json('data.1.gym_id'),
            $page2->json('data.0.gym_id'),
        ];

        $this->assertContains($gym1, $allReturnedGymIds);
        $this->assertContains($gym2, $allReturnedGymIds);
        $this->assertContains($gym3, $allReturnedGymIds);
    }

    public function test_invalid_status_returns_validation_error(): void
    {
        $admin = $this->createPlatformAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/platform/gym-profiles?status=non_existent')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }
}

