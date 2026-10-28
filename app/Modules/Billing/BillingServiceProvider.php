<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;
use App\Modules\Billing\Domain\SubscriptionRepositoryInterface;
use App\Modules\Billing\Infrastructure\EloquentPlatformPlanRepository;
use App\Modules\Billing\Infrastructure\EloquentSubscriptionRepository;
use Illuminate\Support\ServiceProvider;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PlatformPlanRepositoryInterface::class,
            EloquentPlatformPlanRepository::class
        );
        $this->app->bind(
            SubscriptionRepositoryInterface::class,
            EloquentSubscriptionRepository::class
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
