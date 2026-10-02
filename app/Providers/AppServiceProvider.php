<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Domain\Clock;
use App\Shared\Infrastructure\SystemClock;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            Clock::class,
            SystemClock::class
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
