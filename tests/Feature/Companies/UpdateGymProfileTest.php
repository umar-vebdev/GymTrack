<?php

declare(strict_types=1);

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\Role;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertJsonPath('data.amenities', ['shower', 'parking']);

        $this->assertDatabaseHas('companies_gym_profiles', [
            'gym_id' => $gymId,
            'company_id' => $companyId,
            'address' => 'ул. Рудаки, 1',
        ]);
    }

    public function test_editing_approved_profile_returns_it_to_draft(): void
    {
        [$owner, , $gymId] = $this->createOwnerWithGym();
        $this->actingAs($owner, 'sanctum')->putJson("/api/v1/business/gyms/{$gymId}/profile", $this->payload());

        $repo = $this->app->make(GymProfileRepositoryInterface::class);
        $profile = $repo->findByGymId($gymId) ?? $this->fail('Витрина не найдена');
        $profile->submit();
        $profile->approve(new \DateTimeImmutable);
        $repo->save($profile);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/v1/business/gyms/{$gymId}/profile", $this->payload())
            ->assertStatus(200)
            ->assertJsonPath('data.moderation_status', 'draft')
            ->assertJsonPath('data.published_at', null);
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
            ->putJson("/api/v1/business/gyms/{$gymId}/profile", $this->payload())
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'BAD_REQUEST');
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
}
