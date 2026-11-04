<?php

declare(strict_types=1);

namespace Tests\Unit\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymProfile;
use App\Modules\Companies\Domain\GymProfileRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\ModerationStatus;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class GymProfileRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function createGym(): int
    {
        $user = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($user->id, 'Corp');

        return $this->app->make(GymRepositoryInterface::class)->create($companyId, 'Gym', null);
    }

    public function test_it_returns_null_when_profile_does_not_exist(): void
    {
        $repo = $this->app->make(GymProfileRepositoryInterface::class);

        $this->assertNull($repo->findByGymId($this->createGym()));
    }

    public function test_it_saves_and_restores_profile_with_company_id_from_gym(): void
    {
        $gymId = $this->createGym();
        $repo = $this->app->make(GymProfileRepositoryInterface::class);

        $profile = GymProfile::draft($gymId);
        $profile->update('Описание', 'ул. Рудаки, 1', 38.5598, 68.787, '+992900000000', ['mon' => '08:00-22:00'], ['shower']);
        $profile->submit();
        $profile->approve(new DateTimeImmutable('2026-11-03 10:00:00'));
        $repo->save($profile);

        $restored = $repo->findByGymId($gymId);

        $this->assertNotNull($restored);
        $this->assertSame(ModerationStatus::APPROVED, $restored->status());
        $this->assertSame(38.5598, $restored->latitude());
        $this->assertSame(['shower'], $restored->amenities());
        $this->assertSame(['mon' => '08:00-22:00'], $restored->workingHours());
        $this->assertSame('2026-11-03 10:00:00', $restored->publishedAt()?->format('Y-m-d H:i:s'));
        $this->assertSame(
            DB::table('gyms')->where('id', $gymId)->value('company_id'),
            DB::table('companies_gym_profiles')->where('gym_id', $gymId)->value('company_id'),
        );
    }

    public function test_second_save_updates_the_same_row(): void
    {
        $gymId = $this->createGym();
        $repo = $this->app->make(GymProfileRepositoryInterface::class);

        $profile = GymProfile::draft($gymId);
        $repo->save($profile);
        $profile->update('Новое', 'Адрес', null, null, '+992900000000', [], []);
        $repo->save($profile);

        $this->assertSame(1, DB::table('companies_gym_profiles')->count());
        $this->assertSame('Новое', $repo->findByGymId($gymId)?->description());
    }

    public function test_it_paginates_profiles_by_status(): void
    {
        $repo = $this->app->make(GymProfileRepositoryInterface::class);

        $gym1 = $this->createGym();
        $profile1 = GymProfile::draft($gym1);
        $profile1->update('G1', 'Address 1', 38.0, 68.0, '+992900000001', [], []);
        $profile1->submit();
        $repo->save($profile1);

        $gym2 = $this->createGym();
        $profile2 = GymProfile::draft($gym2);
        $profile2->update('G2', 'Address 2', 38.0, 68.0, '+992900000002', [], []);
        $profile2->submit();
        $repo->save($profile2);

        $gym3 = $this->createGym();
        $profile3 = GymProfile::draft($gym3);
        $repo->save($profile3); // remains draft

        $pendingPage1 = $repo->paginateByStatus(ModerationStatus::PENDING, 1, 1);
        $this->assertSame(2, $pendingPage1['total']);
        $this->assertCount(1, $pendingPage1['items']);
        $this->assertSame($gym1, $pendingPage1['items'][0]->gymId());

        $pendingPage2 = $repo->paginateByStatus(ModerationStatus::PENDING, 2, 1);
        $this->assertSame(2, $pendingPage2['total']);
        $this->assertCount(1, $pendingPage2['items']);
        $this->assertSame($gym2, $pendingPage2['items'][0]->gymId());

        $drafts = $repo->paginateByStatus(ModerationStatus::DRAFT, 1, 10);
        $this->assertSame(1, $drafts['total']);
        $this->assertCount(1, $drafts['items']);
        $this->assertSame($gym3, $drafts['items'][0]->gymId());
    }
}
