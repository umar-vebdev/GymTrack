<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application;

use App\Modules\Billing\Contracts\SubscriptionAccessInterface;
use App\Modules\Billing\Domain\SubscriptionRepositoryInterface;
use App\Shared\Domain\Clock;

final class CheckSubscriptionAccess implements SubscriptionAccessInterface
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly Clock $clock
    ) {}

    public function isReadOnly(int $companyId): bool
    {
        $subscription = $this->subscriptions->findByCompanyId($companyId);

        if ($subscription === null) {
            return false;
        }

        $now = $this->clock->now();

        if ($subscription->expireIfDue($now)) {
            $this->subscriptions->save($subscription);
        }

        return $subscription->isReadOnlyAt($now);
    }
}
