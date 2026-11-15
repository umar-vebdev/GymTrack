<?php

declare(strict_types=1);

namespace App\Providers;

use App\Shared\Application\CurrentCompanyInterface;
use App\Shared\Domain\Clock;
use App\Shared\Infrastructure\CurrentCompany;
use App\Shared\Infrastructure\SystemClock;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            CurrentCompanyInterface::class,
            CurrentCompany::class
        );
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
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth-login', function (Request $request): array {
            $identifier = $this->normalizedLoginIdentifier($request);
            $ip = (string) $request->ip();

            $limits = [Limit::perMinute(20)->by('login-ip:'.$ip)];

            if ($identifier !== '') {
                $limits[] = Limit::perMinute(5)->by('login:'.$identifier.'|'.$ip);
            }

            return $limits;
        });

        RateLimiter::for('auth-otp-send', function (Request $request): array {
            $email = $this->normalizedEmail($request);
            $ip = (string) $request->ip();

            $limits = [Limit::perMinute(10)->by('otp-send-ip:'.$ip)];

            if ($email !== '') {
                $limits[] = Limit::perMinute(3)->by('otp-send-email:'.$email);
            }

            return $limits;
        });

        RateLimiter::for('auth-otp-verify', function (Request $request): array {
            $email = $this->normalizedEmail($request);
            $ip = (string) $request->ip();

            $limits = [Limit::perMinute(15)->by('otp-verify-ip:'.$ip)];

            if ($email !== '') {
                $limits[] = Limit::perMinute(5)->by('otp-verify:'.$email.'|'.$ip);
            }

            return $limits;
        });

        RateLimiter::for('auth-register', static function (Request $request): Limit {
            return Limit::perMinute(10)->by('register-ip:'.(string) $request->ip());
        });
    }

    private function normalizedEmail(Request $request): string
    {
        return mb_strtolower(trim((string) $request->input('email', '')));
    }

    private function normalizedLoginIdentifier(Request $request): string
    {
        $email = $this->normalizedEmail($request);
        if ($email !== '') {
            return 'email:'.$email;
        }

        $phone = $this->normalizedPhone($request);
        if ($phone !== '') {
            return 'phone:'.$phone;
        }

        return '';
    }

    private function normalizedPhone(Request $request): string
    {
        $raw = trim((string) $request->input('phone', ''));

        return preg_replace('/\s+/', '', $raw) ?? '';
    }
}
