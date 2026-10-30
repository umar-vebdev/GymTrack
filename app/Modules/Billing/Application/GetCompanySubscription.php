<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application;

use App\Modules\Billing\Domain\Subscription;
use App\Modules\Billing\Domain\SubscriptionRepositoryInterface;
use App\Modules\Billing\Domain\TrialPolicy;
use App\Shared\Domain\Clock;

final class GetCompanySubscription
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly Clock $clock
    ) {}

    public function execute(int $companyId): Subscription
    {
        $subscription = $this->subscriptions->findByCompanyId($companyId);

        if ($subscription === null) {
            $startsAt = $this->clock->now();
            $subscription = $this->subscriptions->createTrial(
                companyId: $companyId,
                startsAt: $startsAt,
                endsAt: TrialPolicy::trialEndsAt($startsAt)
            );
        }

        if ($subscription->expireIfDue($this->clock->now())) {
            $this->subscriptions->save($subscription);
        }

        return $subscription;
    }
}
