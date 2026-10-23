<?php

declare(strict_types=1);

namespace App\Modules\Memberships;

use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Infrastructure\EloquentMembershipTypeRepository;
use Illuminate\Support\ServiceProvider;

final class MembershipsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            MembershipTypeRepositoryInterface::class,
            EloquentMembershipTypeRepository::class
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
