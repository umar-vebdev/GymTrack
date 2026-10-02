<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Application\CurrentCompanyInterface;
use App\Shared\Infrastructure\CurrentCompany;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            CurrentCompanyInterface::class,
            CurrentCompany::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
