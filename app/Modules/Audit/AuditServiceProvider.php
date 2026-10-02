<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Application\Listeners\AuditEventListener;
use App\Modules\Audit\Domain\AuditRepositoryInterface;
use App\Modules\Audit\Infrastructure\EloquentAuditRepository;
use App\Shared\Domain\AuditableEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AuditRepositoryInterface::class,
            EloquentAuditRepository::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        Event::listen(
            AuditableEvent::class,
            AuditEventListener::class
        );
    }
}
