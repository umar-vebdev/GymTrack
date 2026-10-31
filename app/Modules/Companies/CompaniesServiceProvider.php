<?php

declare(strict_types=1);

namespace App\Modules\Companies;

use App\Modules\Companies\Contracts\CompanyCurrencyLookupInterface;
use App\Modules\Companies\Contracts\GymQrLookupInterface;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use App\Modules\Companies\Domain\CompanyRepositoryInterface;
use App\Modules\Companies\Domain\CurrencyRepositoryInterface;
use App\Modules\Companies\Domain\GymRepositoryInterface;
use App\Modules\Companies\Domain\StaffMemberRepositoryInterface;
use App\Modules\Companies\Infrastructure\EloquentCompanyCurrencyLookup;
use App\Modules\Companies\Infrastructure\EloquentCompanyRepository;
use App\Modules\Companies\Infrastructure\EloquentCurrencyRepository;
use App\Modules\Companies\Infrastructure\EloquentGymQrLookup;
use App\Modules\Companies\Infrastructure\EloquentGymRepository;
use App\Modules\Companies\Infrastructure\EloquentStaffCompanyResolver;
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
            CurrencyRepositoryInterface::class,
            EloquentCurrencyRepository::class
        );
        $this->app->bind(
            GymRepositoryInterface::class,
            EloquentGymRepository::class
        );
        $this->app->bind(
            StaffMemberRepositoryInterface::class,
            EloquentStaffMemberRepository::class
        );
        $this->app->bind(
            StaffCompanyResolverInterface::class,
            EloquentStaffCompanyResolver::class
        );
        $this->app->bind(
            GymQrLookupInterface::class,
            EloquentGymQrLookup::class
        );
        $this->app->bind(
            CompanyCurrencyLookupInterface::class,
            EloquentCompanyCurrencyLookup::class
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
