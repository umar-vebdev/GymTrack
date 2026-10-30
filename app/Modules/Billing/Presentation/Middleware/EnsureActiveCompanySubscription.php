<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Middleware;

use App\Models\User;
use App\Modules\Billing\Contracts\SubscriptionAccessInterface;
use App\Modules\Companies\Contracts\StaffCompanyResolverInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Переводит кабинет зала (/api/v1/business/*) в режим «только чтение» при окончании
 * пробного периода или подписки (Бизнес-ТЗ 4.1, 12.8): разрешены все GET-запросы,
 * а изменяющие запросы блокируются с кодом 403 SUBSCRIPTION_EXPIRED.
 */
final class EnsureActiveCompanySubscription
{
    public function __construct(
        private readonly StaffCompanyResolverInterface $companies,
        private readonly SubscriptionAccessInterface $subscriptionAccess
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->is('api/v1/business/*')) {
            return $next($request);
        }

        if ($request->isMethodSafe() || $request->is('api/v1/business/companies') || $request->is('api/v1/business/billing/*')) {
            return $next($request);
        }

        $user = $request->user('sanctum') ?? $request->user();

        if (!$user instanceof User) {
            return $next($request);
        }

        $companyId = $this->companies->companyIdForUser($user->id);

        if ($companyId !== null && $this->subscriptionAccess->isReadOnly($companyId)) {
            return response()->json([
                'error' => [
                    'code' => 'SUBSCRIPTION_EXPIRED',
                    'message' => 'Срок действия подписки или пробного периода истёк. Кабинет зала доступен только в режиме чтения.',
                ],
            ], 403);
        }

        return $next($request);
    }
}
