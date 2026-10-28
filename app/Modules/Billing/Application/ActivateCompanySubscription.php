<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application;

use App\Modules\Billing\Domain\Events\SubscriptionActivated;
use App\Modules\Billing\Domain\Exceptions\PlanNotFound;
use App\Modules\Billing\Domain\PlatformPlanRepositoryInterface;
use App\Modules\Billing\Domain\Subscription;
use App\Modules\Billing\Domain\SubscriptionRepositoryInterface;
use App\Modules\Billing\Domain\TrialPolicy;
use App\Shared\Domain\Clock;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Активирует или продлевает подписку компании по выбранному тарифному плану платформы
 * после подтверждения оплаты администратором платформы (Бизнес-ТЗ 1.1, 2, 4.1).
 */
final class ActivateCompanySubscription
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly PlatformPlanRepositoryInterface $plans,
        private readonly Clock $clock
    ) {}

    /**
     * @throws PlanNotFound
     * @throws DomainException
     */
    public function execute(
        int $companyId,
        int $planId,
        int $activatedByUserId
    ): Subscription {
        $plan = $this->plans->findById($planId);

        if ($plan === null) {
            throw PlanNotFound::withId($planId);
        }

        $subscription = DB::transaction(function () use ($companyId, $plan): Subscription {
            $now = $this->clock->now();
            $subscription = $this->subscriptions->findByCompanyIdForUpdate($companyId);

            if ($subscription === null) {
                $subscription = $this->subscriptions->createTrial(
                    companyId: $companyId,
                    startsAt: $now,
                    endsAt: TrialPolicy::trialEndsAt($now)
                );
            }

            $subscription->activatePlan($plan, $now);
            $this->subscriptions->save($subscription);

            return $subscription;
        });

        Event::dispatch(new SubscriptionActivated(
            subscriptionId: $subscription->id(),
            companyId: $subscription->companyId(),
            planId: $plan->id(),
            endsAt: $subscription->endsAt(),
            activatedByUserId: $activatedByUserId
        ));

        return $subscription;
    }
}

