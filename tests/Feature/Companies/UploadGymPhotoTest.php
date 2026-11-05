<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Application\UploadGymPhoto;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\Exceptions\InvalidGymProfileTransition;
use App\Modules\Companies\Domain\GymPhotoRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class UploadGymPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /** @return array{int, int} */
    private function createGym(): array
    {
        $user = User::factory()->create();
        $company = $this->app->make(CompanyRepositoryInterface::class)->create($user->id, 'Corp');

        return [$company, $this->app->make(GymRepositoryInterface::class)->create($company, 'Gym', null)];
    }

    private function upload(int $company, int $gym): void
    {
        $this->app->make(UploadGymPhoto::class)->execute(
            $company, $gym, UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'),
        );
    }

    public function test_upload_saves_file_and_uses_order_after_last_photo(): void
    {
        [$company, $gym] = $this->createGym();
        $repo = $this->app->make(GymPhotoRepositoryInterface::class);
        $repo->create($company, $gym, 'existing.jpg', 5);
        $this->upload($company, $gym);

        $photos = $repo->listForGym($company, $gym);
        $this->assertCount(2, $photos);
        $this->assertSame(6, $photos[1]->order);
        $this->assertStringStartsWith("companies/{$company}/gyms/{$gym}/", $photos[1]->path);
        Storage::disk('public')->assertExists($photos[1]->path);
        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gym, 'moderation_status' => 'draft']);
    }

    public function test_upload_resets_approval_without_changing_profile_contents(): void
    {
        [$company, $gym] = $this->createGym();
        $profile = GymProfile::draft($gym);
        $profile->update('Description', 'Address', null, null, '+992900000000', [], ['shower']);
        $profile->submit();
        $profile->approve(new DateTimeImmutable);
        $profile->setPublication(true);
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);
        $this->upload($company, $gym);

        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gym, 'moderation_status' => 'draft', 'published_at' => null,
            'description' => 'Description', 'rejection_reason' => null,
            'is_public' => false,
        ]);
    }

    public function test_eleventh_photo_is_rejected_without_writing_a_file(): void
    {
        [$company, $gym] = $this->createGym();
        $repo = $this->app->make(GymPhotoRepositoryInterface::class);
        for ($order = 0; $order < 9; $order++) {
            $repo->create($company, $gym, "photo-{$order}.jpg", $order);
        }
        $this->upload($company, $gym);
        try {
            $this->upload($company, $gym);
            $this->fail('Eleventh photo was accepted');
        } catch (DomainException) {
            $this->assertDatabaseCount('companies_gym_photos', 10);
            $this->assertCount(1, Storage::disk('public')->allFiles());
        }
    }

    public function test_pending_profile_cannot_receive_photos(): void
    {
        [$company, $gym] = $this->createGym();
        $profile = GymProfile::draft($gym);
        $profile->update('Description', 'Address', null, null, '+992900000000', [], []);
        $profile->submit();
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        try {
            $this->upload($company, $gym);
            $this->fail('Pending profile was changed');
        } catch (InvalidGymProfileTransition) {
            $this->assertSame([], Storage::disk('public')->allFiles());
            $this->assertDatabaseCount('companies_gym_photos', 0);
            $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gym, 'moderation_status' => 'pending']);
        }
    }

    public function test_upload_to_foreign_gym_is_rejected_without_side_effects(): void
    {
        [$company] = $this->createGym();
        [, $otherGym] = $this->createGym();
        try {
            $this->upload($company, $otherGym);
            $this->fail('Foreign gym was changed');
        } catch (GymNotFound) {
            $this->assertSame([], Storage::disk('public')->allFiles());
            $this->assertDatabaseCount('companies_gym_photos', 0);
        }
    }

    public function test_database_failure_rolls_back_photo_and_removes_new_file(): void
    {
        [$company, $gym] = $this->createGym();
        $profiles = Mockery::mock(GymProfileRepositoryInterface::class);
        $profiles->shouldReceive('findByGymId')->with($gym)->andReturn(null);
        $profiles->shouldReceive('save')->andThrow(new RuntimeException('Database failure'));
        $this->app->instance(GymProfileRepositoryInterface::class, $profiles);

        try {
            $this->upload($company, $gym);
            $this->fail('Database failure was ignored');
        } catch (RuntimeException $exception) {
            $this->assertSame('Database failure', $exception->getMessage());
            $this->assertSame([], Storage::disk('public')->allFiles());
            $this->assertDatabaseCount('companies_gym_photos', 0);
            $this->assertDatabaseCount('companies_gym_profiles', 0);
        }
    }

    public function test_storage_failure_does_not_create_database_records(): void
    {
        [$company, $gym] = $this->createGym();
        $file = $this->mock(UploadedFile::class);
        $file->shouldReceive('store')->once()->andReturn(false);

        try {
            $this->app->make(UploadGymPhoto::class)->execute($company, $gym, $file);
            $this->fail('Storage failure was ignored');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('companies_gym_photos', 0);
            $this->assertDatabaseCount('companies_gym_profiles', 0);
        }
    }
}
