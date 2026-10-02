<?php

declare(strict_types=1);

namespace Tests\Unit\Companies;

use App\Models\User;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CompanyRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_company_and_gym(): void
    {
        $user = User::factory()->create();

        /** @var CompanyRepositoryInterface $companyRepo */
        $companyRepo = $this->app->make(CompanyRepositoryInterface::class);
        $companyId = $companyRepo->create($user->id, 'GymTrack Corp');

        $company = $companyRepo->findById($companyId);
        $this->assertNotNull($company);
        $this->assertSame('GymTrack Corp', $company->name());
        $this->assertSame($user->id, $company->ownerId());

        /** @var GymRepositoryInterface $gymRepo */
        $gymRepo = $this->app->make(GymRepositoryInterface::class);
        $gymId = $gymRepo->create($companyId, 'Main Gym', 'Dushanbe');

        $gym = $gymRepo->findById($gymId);
        $this->assertNotNull($gym);
        $this->assertSame('Main Gym', $gym->name());
        $this->assertSame('Dushanbe', $gym->address());
        $this->assertSame($companyId, $gym->companyId());
    }
}
