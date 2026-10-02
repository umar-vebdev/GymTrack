<?php

declare(strict_types=1);

namespace App\Modules\Companies;

use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Companies\Infrastructure\EloquentCompanyRepository;
use App\Modules\Companies\Infrastructure\EloquentGymRepository;
use App\Modules\Companies\Infrastructure\EloquentStaffMemberRepository;
use Illuminate\Support\ServiceProvider;

final class CompaniesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            CompanyRepositoryInterface::class,
            EloquentCompanyRepository::class
        );
        $this->app->bind(
            GymRepositoryInterface::class,
            EloquentGymRepository::class
        );
        $this->app->bind(
            StaffMemberRepositoryInterface::class,
            EloquentStaffMemberRepository::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
