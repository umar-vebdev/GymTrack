<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use Illuminate\Support\ServiceProvider;

final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
