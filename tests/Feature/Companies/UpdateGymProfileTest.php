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

final class UpdateGymProfileTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: int, 2: int} owner, companyId, gymId */
    private function createOwnerWithGym(string $company = 'Corp'): array
    {
        $owner = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, $company);
        $this->app->make(StaffMemberRepositoryInterface::class)->create($owner->id, $companyId, Role::OWNER);
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, 'Gym', null);

        return [$owner, $companyId, $gymId];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'description' => 'Лучший зал',
            'address' => 'ул. Рудаки, 1',
            'district' => 'Сино',
            'latitude' => 38.5598,
            'longitude' => 68.787,
            'phone' => '+992900000000',
            'working_hours' => ['mon' => '08:00-22:00'],
            'amenities' => ['shower', 'parking'],
        ];
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->putJson('/api/v1/business/gyms/1/profile', $this->payload())->assertStatus(401);
    }

    public function test_owner_creates_and_updates_profile_as_draft(): void
    {
        [$owner, $companyId, $gymId] = $this->createOwnerWithGym();

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gymId}/profile", $this->payload())
            ->assertStatus(200)
            ->assertJsonPath('data.moderation_status', 'draft')
            ->assertJsonPath('data.latitude', 38.5598)
            ->assertJsonPath('data.district', 'Сино')
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.amenities', ['shower', 'parking']);

        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gymId,
            'company_id' => $companyId,
            'address' => 'ул. Рудаки, 1',
            'district' => 'Сино',
            'is_public' => false,
        ]);
    }

    public function test_editing_approved_profile_returns_it_to_draft(): void
    {
        [$owner, , $gymId] = $this->createOwnerWithGym();
        $this->actingAs($owner, 'sanctum')->putJson("/api/v1/business/gyms/{$gymId}/profile", $this->payload());

        $repo = $this->app->make(GymProfileRepositoryInterface::class);
        $profile = $repo->findByGymId($gymId) ?? $this->fail('Витрина не найдена');
        $profile->submit();
        $profile->approve(new DateTimeImmutable);
        $profile->setPublication(true);
        $repo->save($profile);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gymId}/profile", array_replace($this->payload(), [
                'district' => 'Исмоили Сомони', 'is_public' => true,
            ]))
            ->assertStatus(200)
            ->assertJsonPath('data.moderation_status', 'draft')
            ->assertJsonPath('data.district', 'Исмоили Сомони')
            ->assertJsonPath('data.is_public', false)
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gymId, 'district' => 'Исмоили Сомони', 'is_public' => false,
        ]);
    }

    public function test_profile_on_moderation_cannot_be_edited(): void
    {
        [$owner, , $gymId] = $this->createOwnerWithGym();
        $this->actingAs($owner, 'sanctum')->putJson("/api/v1/business/gyms/{$gymId}/profile", $this->payload());

        $repo = $this->app->make(GymProfileRepositoryInterface::class);
        $profile = $repo->findByGymId($gymId) ?? $this->fail('Витрина не найдена');
        $profile->submit();
        $repo->save($profile);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gymId}/profile", array_replace($this->payload(), ['district' => 'Другой район']))
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');

        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gymId, 'district' => 'Сино', 'moderation_status' => 'pending',
        ]);
    }

    public function test_admin_cannot_update_profile(): void
    {
        [, $companyId, $gymId] = $this->createOwnerWithGym();
        $admin = User::factory()->create();
        $this->app->make(StaffMemberRepositoryInterface::class)->create($admin->id, $companyId, Role::ADMIN);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gymId}/profile", $this->payload())
            ->assertStatus(403);
    }

    public function test_owner_cannot_update_gym_of_another_company(): void
    {
        [$owner] = $this->createOwnerWithGym('Corp A');
        [, , $foreignGymId] = $this->createOwnerWithGym('Corp B');

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$foreignGymId}/profile", $this->payload())
            ->assertStatus(404);

        $this->assertDatabaseMissing('companies_gym_profiles', ['gym_id' => $foreignGymId]);
    }

    public function test_coordinates_are_validated(): void
    {
        [$owner, , $gymId] = $this->createOwnerWithGym();

        $response = $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gymId}/profile", ['latitude' => 95, 'longitude' => 68.7]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');
        $this->assertArrayHasKey('latitude', $response->json('error.details'));

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gymId}/profile", ['latitude' => 38.5])
            ->assertStatus(422);
    }

    public function test_optional_district_is_cleared_when_missing_null_or_blank(): void
    {
        [$owner, , $gym] = $this->createOwnerWithGym();
        foreach ([[], ['district' => null], ['district' => '   ']] as $district) {
            $this->actingAs($owner, 'sanctum')
                ->putJson("/api/v1/business/gyms/{$gym}/profile", $this->payload())->assertOk();
            $payload = $this->payload();
            unset($payload['district']);
            $this->putJson("/api/v1/business/gyms/{$gym}/profile", array_replace($payload, $district))
                ->assertOk()->assertJsonPath('data.district', null);
            $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gym, 'district' => null]);
        }
    }

    public function test_district_accepts_one_hundred_unicode_characters_and_trims_spaces(): void
    {
        [$owner, , $gym] = $this->createOwnerWithGym();
        $district = str_repeat('я', 100);
        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gym}/profile", array_replace($this->payload(), [
                'district' => "  {$district}  ",
            ]))->assertOk()->assertJsonPath('data.district', $district);
        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gym, 'district' => $district]);
    }

    public function test_invalid_district_does_not_change_published_profile(): void
    {
        [$owner, , $gym] = $this->createOwnerWithGym();
        $this->actingAs($owner, 'sanctum')->putJson("/api/v1/business/gyms/{$gym}/profile", $this->payload())->assertOk();
        $repo = $this->app->make(GymProfileRepositoryInterface::class);
        $profile = $repo->findByGymId($gym) ?? $this->fail('Profile not found');
        $profile->submit();
        $profile->approve(new DateTimeImmutable);
        $profile->setPublication(true);
        $repo->save($profile);
        foreach ([str_repeat('я', 101), ['Сино'], 123] as $district) {
            $this->putJson("/api/v1/business/gyms/{$gym}/profile", array_replace($this->payload(), ['district' => $district]))
                ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonStructure(['error' => ['details' => ['district']]]);
            $this->assertDatabaseHas('companies_gym_profiles', [
                'gym_id' => $gym, 'district' => 'Сино', 'moderation_status' => 'approved', 'is_public' => true,
            ]);
        }
    }

    public function test_update_reads_profile_after_locking_gym(): void
    {
        [$owner, , $gym] = $this->createOwnerWithGym();
        $realGyms = $this->app->make(GymRepositoryInterface::class);
        $realProfiles = $this->app->make(GymProfileRepositoryInterface::class);
        $pending = GymProfile::draft($gym);
        $pending->update('Исходный', 'ул. Рудаки, 1', null, null, '+992900000000', [], [], 'Сино');
        $pending->submit();
        $realProfiles->save($pending);

        $locked = false;
        $gyms = Mockery::mock(GymRepositoryInterface::class);
        $gyms->shouldReceive('findByIdForUpdate')->once()->with($gym)->andReturnUsing(
            function (int $id) use ($realGyms, &$locked): ?Gym {
                $this->assertGreaterThan(1, DB::transactionLevel());
                $locked = true;

                return $realGyms->findByIdForUpdate($id);
            }
        );
        $profiles = Mockery::mock(GymProfileRepositoryInterface::class);
        $profiles->shouldReceive('findByGymId')->once()->with($gym)->andReturnUsing(
            function (int $id) use ($realProfiles, &$locked): ?GymProfile {
                $this->assertTrue($locked, 'Profile must be read only after locking the gym row.');

                return $realProfiles->findByGymId($id);
            }
        );
        $this->app->instance(GymRepositoryInterface::class, $gyms);
        $this->app->instance(GymProfileRepositoryInterface::class, $profiles);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gym}/profile", $this->payload())
            ->assertBadRequest()->assertJsonPath('error.code', 'BAD_REQUEST');
        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gym, 'description' => 'Исходный', 'moderation_status' => 'pending',
        ]);
    }

    public function test_update_rolls_back_partial_database_changes_on_save_failure(): void
    {
        [$owner, , $gym] = $this->createOwnerWithGym();
        $realProfiles = $this->app->make(GymProfileRepositoryInterface::class);
        $approved = GymProfile::draft($gym);
        $approved->update('Исходный', 'ул. Рудаки, 1', null, null, '+992900000000', [], [], 'Сино');
        $approved->submit();
        $approved->approve(new DateTimeImmutable);
        $approved->setPublication(true);
        $realProfiles->save($approved);

        $failingProfiles = Mockery::mock(GymProfileRepositoryInterface::class);
        $failingProfiles->shouldReceive('findByGymId')->once()->with($gym)->andReturn($approved);
        $failingProfiles->shouldReceive('save')->once()->andReturnUsing(function (GymProfile $changed): void {
            DB::table('companies_gym_profiles')->where('gym_id', $changed->gymId())->update([
                'moderation_status' => 'draft', 'is_public' => false,
            ]);
            throw new RuntimeException('Database failure');
        });
        $this->app->instance(GymProfileRepositoryInterface::class, $failingProfiles);
        config(['app.debug' => false]);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gym}/profile", $this->payload())
            ->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');
        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gym, 'moderation_status' => 'approved', 'is_public' => true,
        ]);
    }
}
