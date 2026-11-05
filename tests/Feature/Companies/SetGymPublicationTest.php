<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Application\SetGymPublication;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\Exceptions\GymNotFound;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class SetGymPublicationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, int, int} */
    private function createOwnerWithProfile(ModerationStatus $status = ModerationStatus::APPROVED, bool $isPublic = false): array
    {
        $owner = User::factory()->create();
        $company = $this->app->make(CompanyRepositoryInterface::class)->create($owner->id, 'Corp');
        $this->app->make(StaffMemberRepositoryInterface::class)->create($owner->id, $company, Role::OWNER);
        $gym = $this->app->make(GymRepositoryInterface::class)->create($company, 'Gym', null);
        $profile = GymProfile::draft($gym);
        $profile->update('Description', 'Address', null, null, '+992900000000', [], ['shower'], 'Сино');
        if ($status !== ModerationStatus::DRAFT) {
            $profile->submit();
        }
        if ($status === ModerationStatus::APPROVED) {
            $profile->approve(new DateTimeImmutable);
        } elseif ($status === ModerationStatus::REJECTED) {
            $profile->reject('Уточните адрес.');
        }
        $profile->setPublication($isPublic);
        $this->app->make(GymProfileRepositoryInterface::class)->save($profile);

        return [$owner, $company, $gym];
    }

    /** @return array<string, array{ModerationStatus, bool, int}> */
    public static function publicationStates(): array
    {
        $cases = [];
        foreach (ModerationStatus::cases() as $status) {
            $cases[$status->value.' enable'] = [$status, true, $status === ModerationStatus::APPROVED ? 200 : 400];
            $cases[$status->value.' disable'] = [$status, false, 200];
        }

        return $cases;
    }

    #[DataProvider('publicationStates')]
    public function test_owner_can_enable_only_approved_profile_and_disable_in_every_status(
        ModerationStatus $status,
        bool $isPublic,
        int $expectedStatus,
    ): void {
        [$owner, , $gym] = $this->createOwnerWithProfile($status, $status === ModerationStatus::APPROVED && !$isPublic);
        $before = DB::table('companies_gym_profiles')->where('gym_id', $gym)->first();
        $url = "/api/v1/business/gyms/{$gym}/publication";
        $response = $this->actingAs($owner, 'sanctum')->patchJson($url, [
            'is_public' => $isPublic, 'district' => 'Ignored', 'moderation_status' => 'approved',
        ])->assertStatus($expectedStatus);
        if ($expectedStatus === 400) {
            $response->assertJsonPath('error.code', 'BAD_REQUEST');
            $this->assertEquals($before, DB::table('companies_gym_profiles')->where('gym_id', $gym)->first());

            return;
        }
        $response->assertJsonPath('data.is_public', $isPublic)
            ->assertJsonPath('data.moderation_status', $status->value)
            ->assertJsonPath('data.district', 'Сино')
            ->assertJsonPath('data.description', 'Description');
        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gym, 'is_public' => $isPublic, 'moderation_status' => $status->value,
        ]);
        $this->patchJson($url, ['is_public' => $isPublic])->assertOk()->assertExactJson($response->json());
        $this->assertDatabaseCount('companies_gym_profiles', 1);
    }

    public function test_guest_cannot_change_publication(): void
    {
        $this->patchJson('/api/v1/business/gyms/1/publication', ['is_public' => true])
            ->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_admin_and_non_staff_cannot_change_publication(): void
    {
        [, $company, $gym] = $this->createOwnerWithProfile();
        $admin = User::factory()->create();
        $this->app->make(StaffMemberRepositoryInterface::class)->create($admin->id, $company, Role::ADMIN);
        foreach ([$admin, User::factory()->create()] as $user) {
            $this->actingAs($user, 'sanctum')
                ->patchJson("/api/v1/business/gyms/{$gym}/publication", ['is_public' => true])
                ->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        }
        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gym, 'is_public' => false]);
    }

    public function test_foreign_and_missing_gyms_are_not_accessible(): void
    {
        [$owner] = $this->createOwnerWithProfile();
        [, , $foreignGym] = $this->createOwnerWithProfile();
        foreach ([$foreignGym, 999999] as $gym) {
            $this->actingAs($owner, 'sanctum')
                ->patchJson("/api/v1/business/gyms/{$gym}/publication", ['is_public' => true])
                ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        }
        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $foreignGym, 'is_public' => false]);
    }

    public function test_missing_or_invalid_boolean_does_not_change_published_profile(): void
    {
        [$owner, , $gym] = $this->createOwnerWithProfile(ModerationStatus::APPROVED, true);
        foreach ([[], ['is_public' => null], ['is_public' => 'true'], ['is_public' => 'false'], ['is_public' => 2], ['is_public' => []]] as $payload) {
            $this->actingAs($owner, 'sanctum')
                ->patchJson("/api/v1/business/gyms/{$gym}/publication", $payload)
                ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')
                ->assertJsonStructure(['error' => ['details' => ['is_public']]]);
        }
        $this->assertDatabaseHas('companies_gym_profiles', ['gym_id' => $gym, 'is_public' => true]);
    }

    public function test_disabling_a_missing_profile_creates_a_private_draft(): void
    {
        [$owner, $company] = $this->createOwnerWithProfile();
        $gym = $this->app->make(GymRepositoryInterface::class)->create($company, 'New gym', null);
        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/business/gyms/{$gym}/publication", ['is_public' => true])
            ->assertBadRequest()->assertJsonPath('error.code', 'BAD_REQUEST');
        $this->assertDatabaseMissing('companies_gym_profiles', ['gym_id' => $gym]);
        $this->patchJson("/api/v1/business/gyms/{$gym}/publication", ['is_public' => false])
            ->assertOk()->assertJsonPath('data.is_public', false)->assertJsonPath('data.moderation_status', 'draft');
    }

    public function test_application_also_rejects_a_gym_from_another_company(): void
    {
        [, $company] = $this->createOwnerWithProfile();
        [, , $foreignGym] = $this->createOwnerWithProfile();
        $this->expectException(GymNotFound::class);
        $this->app->make(SetGymPublication::class)->execute($company, $foreignGym, true);
    }

    public function test_storage_exception_rolls_back_partial_database_changes(): void
    {
        [$owner, , $gym] = $this->createOwnerWithProfile();
        $profile = $this->app->make(GymProfileRepositoryInterface::class)->findByGymId($gym);
        $profiles = Mockery::mock(GymProfileRepositoryInterface::class);
        $profiles->shouldReceive('findByGymId')->with($gym)->andReturn($profile);
        $profiles->shouldReceive('save')->andReturnUsing(function (GymProfile $changed): void {
            DB::table('companies_gym_profiles')->where('gym_id', $changed->gymId())->update(['is_public' => true]);
            throw new RuntimeException('Database failure');
        });
        $this->app->instance(GymProfileRepositoryInterface::class, $profiles);
        config(['app.debug' => false]);
        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/business/gyms/{$gym}/publication", ['is_public' => true])
            ->assertStatus(500)->assertJsonPath('error.code', 'INTERNAL_ERROR');
        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gym, 'is_public' => false, 'moderation_status' => 'approved',
        ]);
    }
}
