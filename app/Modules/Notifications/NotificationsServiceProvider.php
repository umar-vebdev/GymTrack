<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Application\Listeners\SendMembershipRequestPaidNotificationListener;
use App\Modules\Notifications\Application\Listeners\SendVisitNotificationListener;
use App\Modules\Notifications\Application\NotificationSender;
use App\Modules\Notifications\Domain\DeviceTokenRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationLogRepositoryInterface;
use App\Modules\Notifications\Domain\PushGatewayInterface;
use App\Modules\Notifications\Domain\SmsGatewayInterface;
use App\Modules\Notifications\Infrastructure\EloquentDeviceTokenRepository;
use App\Modules\Notifications\Infrastructure\EloquentNotificationLogRepository;
use App\Modules\Notifications\Infrastructure\LogNotificationsSmsGateway;
use App\Modules\Notifications\Infrastructure\LogPushGateway;
use App\Modules\Notifications\Presentation\Console\SendExpiringMembershipRemindersCommand;
use App\Modules\Sales\Domain\Events\MembershipRequestPaid;
use App\Modules\Visits\Domain\Events\VisitRegistered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PushGatewayInterface::class, LogPushGateway::class);
        $this->app->bind(SmsGatewayInterface::class, LogNotificationsSmsGateway::class);
        $this->app->bind(NotificationLogRepositoryInterface::class, EloquentNotificationLogRepository::class);
        $this->app->bind(DeviceTokenRepositoryInterface::class, EloquentDeviceTokenRepository::class);

        $this->app->singleton(NotificationSender::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');
        $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');

        Event::listen(
            VisitRegistered::class,
            SendVisitNotificationListener::class
        );

        Event::listen(
            MembershipRequestPaid::class,
            SendMembershipRequestPaidNotificationListener::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                SendExpiringMembershipRemindersCommand::class,
            ]);
        }
    }
}
