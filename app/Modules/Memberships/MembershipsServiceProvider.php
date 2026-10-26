<?php

declare(strict_types=1);

namespace App\Modules\Memberships;

use App\Modules\Memberships\Application\FindUsableMembership;
use App\Modules\Memberships\Application\IssueMembership;
use App\Modules\Memberships\Contracts\MembershipIssuerInterface;
use App\Modules\Memberships\Contracts\MembershipLookupInterface;
use App\Modules\Memberships\Domain\MembershipRepositoryInterface;
use App\Modules\Memberships\Domain\MembershipTypeRepositoryInterface;
use App\Modules\Memberships\Infrastructure\EloquentMembershipRepository;
use App\Modules\Memberships\Infrastructure\EloquentMembershipTypeRepository;
use App\Modules\Memberships\Presentation\Console\ExpireDueMembershipsCommand;
use Illuminate\Support\ServiceProvider;

final class MembershipsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            MembershipLookupInterface::class,
            FindUsableMembership::class
        );
        $this->app->bind(
            MembershipIssuerInterface::class,
            IssueMembership::class
        );
        $this->app->bind(
            MembershipRepositoryInterface::class,
            EloquentMembershipRepository::class
        );
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireDueMembershipsCommand::class,
            ]);
        }
    }
}
