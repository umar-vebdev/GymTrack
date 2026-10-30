<?php

declare(strict_types=1);

namespace App\Modules\Visits;

use App\Modules\Visits\Application\CheckInByQr;
use App\Modules\Visits\Application\CheckInManually;
use App\Modules\Visits\Domain\QrTokenServiceInterface;
use App\Modules\Visits\Domain\Rules\CheckInRuleInterface;
use App\Modules\Visits\Domain\Rules\DailyLimitNotExceededRule;
use App\Modules\Visits\Domain\Rules\MembershipIsAvailableRule;
use App\Modules\Visits\Domain\Rules\NotFrozenRule;
use App\Modules\Visits\Domain\Rules\WithinAllowedTimeRule;
use App\Modules\Visits\Domain\VisitRepositoryInterface;
use App\Modules\Visits\Infrastructure\EloquentVisitRepository;
use App\Modules\Visits\Infrastructure\HmacQrTokenService;
use Illuminate\Support\ServiceProvider;

final class VisitsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            QrTokenServiceInterface::class,
            HmacQrTokenService::class,
        );
        $this->app->bind(
            VisitRepositoryInterface::class,
            EloquentVisitRepository::class,
        );

        $this->app->tag([
            NotFrozenRule::class,
            MembershipIsAvailableRule::class,
            DailyLimitNotExceededRule::class,
            WithinAllowedTimeRule::class,
        ], CheckInRuleInterface::class);

        $this->app->when([CheckInByQr::class, CheckInManually::class])
            ->needs('$rules')
            ->giveTagged(CheckInRuleInterface::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Infrastructure/Migrations');

        if (file_exists(__DIR__.'/Presentation/routes.php')) {
            $this->loadRoutesFrom(__DIR__.'/Presentation/routes.php');
        }
    }
}
