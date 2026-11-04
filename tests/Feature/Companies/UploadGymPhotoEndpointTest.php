<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymPhotoRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UploadGymPhotoEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
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

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');
    }

    public function test_guest_cannot_upload(): void
    {
        $this->postJson('/api/v1/business/gyms/1/photos', ['photo' => $this->photo()])
            ->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    /** @return array<string, array{string, string}> */
    public static function imageFormats(): array
    {
        return [
            'jpeg' => ['photo.jpg', 'image/jpeg'],
            'png' => ['photo.png', 'image/png'],
            'webp' => ['photo.webp', 'image/webp'],
        ];
    }

    #[DataProvider('imageFormats')]
    public function test_owner_uploads_supported_image(string $name, string $mime): void
    {
        [$owner, $company, $gym] = $this->createOwnerWithGym();
        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gym}/photos", [
                'photo' => UploadedFile::fake()->create($name, 5120, $mime),
            ])->assertCreated()
            ->assertJsonPath('data.gym_id', $gym)
            ->assertJsonPath('data.order', 0)
            ->assertJsonStructure(['data' => ['id', 'gym_id', 'url', 'order', 'created_at']]);

        $photos = $this->app->make(GymPhotoRepositoryInterface::class)->listForGym($company, $gym);
        $this->assertCount(1, $photos);
        $this->assertSame(Storage::disk('public')->url($photos[0]->path), $response->json('data.url'));
        Storage::disk('public')->assertExists($photos[0]->path);
    }

    public function test_admin_and_user_without_company_cannot_upload(): void
    {
        [, $company, $gym] = $this->createOwnerWithGym();
        $admin = User::factory()->create();
        $this->app->make(StaffMemberRepositoryInterface::class)->create($admin->id, $company, Role::ADMIN);
        foreach ([$admin, User::factory()->create()] as $user) {
            $this->actingAs($user, 'sanctum')
                ->postJson("/api/v1/business/gyms/{$gym}/photos", ['photo' => $this->photo()])
                ->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        }
        $this->assertDatabaseCount('companies_gym_photos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_owner_cannot_upload_to_foreign_or_missing_gym(): void
    {
        [$owner] = $this->createOwnerWithGym();
        [, , $foreignGym] = $this->createOwnerWithGym();
        foreach ([$foreignGym, 999999] as $gym) {
            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/v1/business/gyms/{$gym}/photos", ['photo' => $this->photo()])
                ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        }
        $this->assertDatabaseCount('companies_gym_photos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_invalid_or_missing_photos_are_rejected(): void
    {
        [$owner, , $gym] = $this->createOwnerWithGym();
        $disguised = UploadedFile::fake()->createWithContent('photo.jpg', 'This is not an image');
        $files = [
            null,
            UploadedFile::fake()->create('large.jpg', 5121, 'image/jpeg'),
            UploadedFile::fake()->create('animation.gif', 10, 'image/gif'),
            new UploadedFile($disguised->getPathname(), 'photo.jpg', null, null, true),
            UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ];
        foreach ($files as $file) {
            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/v1/business/gyms/{$gym}/photos", ['photo' => $file])
                ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonStructure(['error' => ['details' => ['photo']]]);
        }
        $this->assertDatabaseCount('companies_gym_photos', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_real_png_is_accepted_by_content(): void
    {
        [$owner, , $gym] = $this->createOwnerWithGym();
        $fixture = UploadedFile::fake()->createWithContent(
            'photo.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6tAAAAABJRU5ErkJggg=='),
        );
        $file = new UploadedFile($fixture->getPathname(), 'photo.png', null, null, true);
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gym}/photos", ['photo' => $file])
            ->assertCreated();
    }

    public function test_photo_limit_returns_bad_request(): void
    {
        [$owner, $company, $gym] = $this->createOwnerWithGym();
        $photos = $this->app->make(GymPhotoRepositoryInterface::class);
        for ($order = 0; $order < 10; $order++) {
            $photos->create($company, $gym, "{$order}.jpg", $order);
        }
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gym}/photos", ['photo' => $this->photo()])
            ->assertBadRequest()->assertJsonPath('error.code', 'BAD_REQUEST');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_profile_on_moderation_rejects_upload(): void
    {
        [$owner, , $gym] = $this->createOwnerWithGym();
        $profile = GymProfile::draft($gym);
        $profile->update('Description', 'Address', null, null, '+992900000000', [], []);
        $profile->submit();
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/business/gyms/{$gym}/photos", ['photo' => $this->photo()])
            ->assertBadRequest()->assertJsonPath('error.code', 'BAD_REQUEST');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
