<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymPhoto;
use App\Modules\Companies\Domain\GymPhotoRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class DeleteGymPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['app.debug' => false]);
    }

    /** @return array{User, int, int} */
    private function createOwnerWithGym(): array
    {
        $owner = User::factory()->create();
        $company = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, 'Corp');
        $this->app->make(StaffMemberRepositoryInterface::class)->create($owner->id, $company, Role::OWNER);
        $gym = $this->app->make(GymRepositoryInterface::class)->create($company, 'Gym', null);

        return [$owner, $company, $gym];
    }

    private function photo(int $company, int $gym): GymPhoto
    {
        $path = "companies/{$company}/gyms/{$gym}/photo.jpg";
        Storage::disk('public')->put($path, 'photo contents');

        return $this->app->make(GymPhotoRepositoryInterface::class)->create($company, $gym, $path, 0);
    }

    private function profile(int $gym, bool $approved = true): GymProfile
    {
        $profile = GymProfile::draft($gym);
        $profile->update('Description', 'Address', null, null, '+992900000000', [], []);
        $profile->submit();
        if ($approved) {
            $profile->approve(new DateTimeImmutable);
        }
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        return $profile;
    }

    public function test_owner_deletes_file_and_record_and_resets_approved_profile(): void
    {
        [$owner, $company, $gym] = $this->createOwnerWithGym();
        $photo = $this->photo($company, $gym);
        $this->profile($gym);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/business/gyms/{$gym}/photos/{$photo->id}")
            ->assertOk()->assertExactJson(['data' => null]);

        Storage::disk('public')->assertMissing($photo->path);
        $this->assertDatabaseMissing('companies_gym_photos', ['id' => $photo->id]);
        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gym, 'moderation_status' => 'draft', 'published_at' => null,
            'rejection_reason' => null, 'description' => 'Description',
        ]);
    }

    public function test_guest_cannot_delete_photo(): void
    {
        $this->deleteJson('/api/v1/business/gyms/1/photos/1')
            ->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_admin_and_non_staff_cannot_delete_photo(): void
    {
        [, $company, $gym] = $this->createOwnerWithGym();
        $photo = $this->photo($company, $gym);
        $admin = User::factory()->create();
        $this->app->make(StaffMemberRepositoryInterface::class)->create($admin->id, $company, Role::ADMIN);
        foreach ([$admin, User::factory()->create()] as $user) {
            $this->actingAs($user, 'sanctum')
                ->deleteJson("/api/v1/business/gyms/{$gym}/photos/{$photo->id}")
                ->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        }
        Storage::disk('public')->assertExists($photo->path);
        $this->assertDatabaseHas('companies_gym_photos', ['id' => $photo->id]);
    }

    public function test_foreign_company_gym_and_photo_are_not_accessible(): void
    {
        [$owner, , $ownGym] = $this->createOwnerWithGym();
        [, $company, $gym] = $this->createOwnerWithGym();
        $photo = $this->photo($company, $gym);
        foreach ([$gym, $ownGym, 999999] as $targetGym) {
            $this->actingAs($owner, 'sanctum')
                ->deleteJson("/api/v1/business/gyms/{$targetGym}/photos/{$photo->id}")
                ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        }
        Storage::disk('public')->assertExists($photo->path);
        $this->assertDatabaseHas('companies_gym_photos', ['id' => $photo->id]);
        $this->assertDatabaseCount('companies_gym_profiles', 0);
    }

    public function test_photo_of_another_gym_in_same_company_is_preserved(): void
    {
        [$owner, $company, $gym] = $this->createOwnerWithGym();
        $otherGym = $this->app->make(GymRepositoryInterface::class)->create($company, 'Other', null);
        $photo = $this->photo($company, $otherGym);
        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/business/gyms/{$gym}/photos/{$photo->id}")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        Storage::disk('public')->assertExists($photo->path);
        $this->assertDatabaseHas('companies_gym_photos', ['id' => $photo->id]);
    }

    public function test_profile_in_pending_cannot_be_changed(): void
    {
        [$owner, $company, $gym] = $this->createOwnerWithGym();
        $photo = $this->photo($company, $gym);
        $this->profile($gym, false);
        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/business/gyms/{$gym}/photos/{$photo->id}")
            ->assertBadRequest()->assertJsonPath('error.code', 'BAD_REQUEST');
        Storage::disk('public')->assertExists($photo->path);
        $this->assertDatabaseHas('companies_gym_photos', ['id' => $photo->id]);
        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gym, 'moderation_status' => 'pending']);
    }

    public function test_repeated_delete_returns_not_found(): void
    {
        [$owner, $company, $gym] = $this->createOwnerWithGym();
        $photo = $this->photo($company, $gym);
        $url = "/api/v1/business/gyms/{$gym}/photos/{$photo->id}";
        $this->actingAs($owner, 'sanctum')->deleteJson($url)->assertOk();
        $this->deleteJson($url)->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_missing_file_does_not_prevent_deleting_the_record(): void
    {
        [$owner, $company, $gym] = $this->createOwnerWithGym();
        $photo = $this->photo($company, $gym);
        Storage::disk('public')->delete($photo->path);
        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/business/gyms/{$gym}/photos/{$photo->id}")->assertOk();
        $this->assertDatabaseMissing('companies_gym_photos', ['id' => $photo->id]);
    }

    public function test_database_failure_preserves_file_and_rolls_back_deleted_record(): void
    {
        [$owner, $company, $gym] = $this->createOwnerWithGym();
        $photo = $this->photo($company, $gym);
        $profile = $this->profile($gym);
        $profiles = Mockery::mock(GymProfileRepositoryInterface::class);
        $profiles->shouldReceive('findByGymId')->with($gym)->andReturn($profile);
        $profiles->shouldReceive('save')->andThrow(new RuntimeException('Database failure'));
        $this->app->instance(GymProfileRepositoryInterface::class, $profiles);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/business/gyms/{$gym}/photos/{$photo->id}")
            ->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');
        Storage::disk('public')->assertExists($photo->path);
        $this->assertDatabaseHas('companies_gym_photos', ['id' => $photo->id]);
        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gym, 'moderation_status' => 'approved']);
    }

    public function test_partial_storage_failure_restores_file_and_rolls_back_database_changes(): void
    {
        [$owner, $company, $gym] = $this->createOwnerWithGym();
        $photo = $this->photo($company, $gym);
        $this->profile($gym);
        $disk = Storage::disk('public');
        $failing = Mockery::mock(FilesystemAdapter::class);
        $failing->shouldReceive('exists')->with($photo->path)->andReturn(true, false);
        $failing->shouldReceive('get')->with($photo->path)->andReturn('photo contents');
        $failing->shouldReceive('delete')->with($photo->path)->andReturnUsing(function () use ($disk, $photo): bool {
            $disk->delete($photo->path);

            return false;
        });
        $failing->shouldReceive('put')->with($photo->path, 'photo contents')
            ->andReturnUsing(fn (string $path, string $contents): bool => $disk->put($path, $contents) === true);
        Storage::shouldReceive('disk')->with('public')->andReturn($failing);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/business/gyms/{$gym}/photos/{$photo->id}")
            ->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');
        $this->assertSame('photo contents', $disk->get($photo->path));
        $this->assertDatabaseHas('companies_gym_photos', ['id' => $photo->id]);
        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gym, 'moderation_status' => 'approved']);
    }
}
