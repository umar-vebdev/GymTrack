<?php

declare(strict_types=1);

namespace App\Modules\Clients;

use App\Modules\Clients\Application\Listeners\LinkUserToClients;
use App\Modules\Clients\Domain\ClientRepositoryInterface;
use App\Modules\Clients\Infrastructure\EloquentClientRepository;
use App\Modules\Identity\Contracts\PhoneVerified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ClientsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            ClientRepositoryInterface::class,
            EloquentClientRepository::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        Event::listen(PhoneVerified::class, [LinkUserToClients::class, 'handle']);

        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
