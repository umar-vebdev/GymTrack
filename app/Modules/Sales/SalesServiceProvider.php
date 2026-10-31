<?php

declare(strict_types=1);

namespace App\Modules\Sales;

use App\Modules\Sales\Contracts\SalesAnalyticsQueryInterface;
use App\Modules\Sales\Domain\MembershipRequestRepositoryInterface;
use App\Modules\Sales\Domain\ProductRepositoryInterface;
use App\Modules\Sales\Domain\SaleRepositoryInterface;
use App\Modules\Sales\Infrastructure\EloquentMembershipRequestRepository;
use App\Modules\Sales\Infrastructure\EloquentProductRepository;
use App\Modules\Sales\Infrastructure\EloquentSaleRepository;
use App\Modules\Sales\Infrastructure\EloquentSalesAnalyticsQuery;
use Illuminate\Support\ServiceProvider;

final class SalesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ProductRepositoryInterface::class,
            EloquentProductRepository::class
        );
        $this->app->bind(
            SaleRepositoryInterface::class,
            EloquentSaleRepository::class
        );
        $this->app->bind(
            MembershipRequestRepositoryInterface::class,
            EloquentMembershipRequestRepository::class
        );
        $this->app->bind(
            SalesAnalyticsQueryInterface::class,
            EloquentSalesAnalyticsQuery::class
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
