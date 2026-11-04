<?php

declare(strict_types=1);

namespace Tests\Unit\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymPhotoRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Infrastructure\Persistence\GymPhotoModel;
use App\Shared\Application\CurrentCompanyInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GymPhotoRepositoryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{int, int} */
    private function createGym(): array
    {
        $user = User::factory()->create();
        $companyId = $this->app->make(CompanyRepositoryInterface::class)->create($user->id, 'Corp');
        $gymId = $this->app->make(GymRepositoryInterface::class)->create($companyId, 'Gym', null);

        return [$companyId, $gymId];
    }

    public function test_it_restores_photos_in_display_order_with_a_stable_tie_breaker(): void
    {
        [$companyId, $gymId] = $this->createGym();
        $repo = $this->app->make(GymPhotoRepositoryInterface::class);
        $last = $repo->create($companyId, $gymId, 'gyms/last.webp', 2);
        $first = $repo->create($companyId, $gymId, 'gyms/first.jpg', 0);
        $second = $repo->create($companyId, $gymId, 'gyms/second.png', 0);
        $photos = $repo->listForGym($companyId, $gymId);

        $this->assertSame([$first->id, $second->id, $last->id], array_column($photos, 'id'));
        $this->assertSame($companyId, $photos[0]->companyId);
        $this->assertSame($gymId, $photos[0]->gymId);
        $this->assertSame('gyms/first.jpg', $photos[0]->path);
        $this->assertSame(0, $photos[0]->order);
        $this->assertEquals($first->createdAt, $photos[0]->createdAt);
    }

    public function test_company_and_gym_are_required_to_read_and_delete_photos(): void
    {
        [$companyId, $gymId] = $this->createGym();
        [$otherCompanyId, $otherGymId] = $this->createGym();
        $sameCompanyGym = $this->app->make(GymRepositoryInterface::class)->create($companyId, 'Other', null);
        $repo = $this->app->make(GymPhotoRepositoryInterface::class);
        $photo = $repo->create($companyId, $gymId, 'gyms/photo.jpg', 0);
        $other = $repo->create($otherCompanyId, $otherGymId, 'gyms/other.jpg', 0);

        $this->assertSame([], $repo->listForGym($otherCompanyId, $gymId));
        $this->assertSame([], $repo->listForGym($companyId, $sameCompanyGym));
        $this->assertFalse($repo->delete($otherCompanyId, $gymId, $photo->id));
        $this->assertFalse($repo->delete($companyId, $sameCompanyGym, $photo->id));
        $this->assertTrue($repo->delete($companyId, $gymId, $photo->id));
        $this->assertFalse($repo->delete($companyId, $gymId, $photo->id));
        $this->assertSame([$other->id], array_column($repo->listForGym($otherCompanyId, $otherGymId), 'id'));
    }

    public function test_it_rejects_creating_a_photo_for_another_companys_gym(): void
    {
        [$companyId] = $this->createGym();
        [, $otherGymId] = $this->createGym();

        $this->expectException(ModelNotFoundException::class);
        $this->app->make(GymPhotoRepositoryInterface::class)->create($companyId, $otherGymId, 'photo.jpg', 0);
    }

    public function test_current_company_scope_cannot_be_bypassed_with_explicit_ids(): void
    {
        [$companyId] = $this->createGym();
        [$otherCompanyId, $otherGymId] = $this->createGym();
        $repo = $this->app->make(GymPhotoRepositoryInterface::class);
        $photo = $repo->create($otherCompanyId, $otherGymId, 'other.jpg', 0);
        $this->app->make(CurrentCompanyInterface::class)->setId($companyId);

        $this->assertSame(0, GymPhotoModel::query()->count());
        $this->assertSame([], $repo->listForGym($otherCompanyId, $otherGymId));
        $this->assertFalse($repo->delete($otherCompanyId, $otherGymId, $photo->id));
        $this->assertDatabaseHas('companies_gym_photos', ['id' => $photo->id]);

        $this->expectException(ModelNotFoundException::class);
        $repo->create($otherCompanyId, $otherGymId, 'forbidden.jpg', 1);
    }
}
