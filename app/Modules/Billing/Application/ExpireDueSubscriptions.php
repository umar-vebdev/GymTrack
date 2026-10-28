<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application;

use App\Modules\Billing\Domain\SubscriptionRepositoryInterface;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Идемпотентный сценарий ежедневной проверки пробного периода и подписок залов (Тех. ТЗ 9.4):
 * переводит истёкшие подписки в статус expired.
 */
final class ExpireDueSubscriptions
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly Clock $clock
    ) {}

    public function execute(?int $companyId = null): int
    {
        $now = $this->clock->now();

        if ($companyId !== null) {
            return DB::transaction(function () use ($companyId, $now): int {
                $subscription = $this->subscriptions->findByCompanyIdForUpdate($companyId);
                if ($subscription === null || !$subscription->expireIfDue($now)) {
                    return 0;
                }

                $this->subscriptions->save($subscription);

                return 1;
            });
        }

        $due = $this->subscriptions->listDueForExpiry($now);
        $expiredCount = 0;

        foreach ($due as $item) {
            $expiredCount += $this->execute($item->companyId());
        }

        return $expiredCount;
    }
}

