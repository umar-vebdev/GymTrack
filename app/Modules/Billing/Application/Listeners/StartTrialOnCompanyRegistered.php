<?php

declare(strict_types=1);

namespace App\Modules\Billing\Application\Listeners;

use App\Modules\Billing\Domain\SubscriptionRepositoryInterface;
use App\Modules\Billing\Domain\TrialPolicy;
use App\Modules\Companies\Contracts\CompanyRegistered;
use App\Shared\Domain\Clock;

/**
 * Автоматически запускает 14-дневный бесплатный пробный период при регистрации новой компании (Бизнес-ТЗ 1.1, 4.1).
 * Операция идемпотентна: если подписка для компании уже существует, повторно не создаётся.
 */
final class StartTrialOnCompanyRegistered
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly Clock $clock
    ) {}

    public function handle(CompanyRegistered $event): void
    {
        if ($this->subscriptions->findByCompanyId($event->companyId) !== null) {
            return;
        }

        $startsAt = $this->clock->now();
        $endsAt = TrialPolicy::trialEndsAt($startsAt);

        $this->subscriptions->createTrial(
            companyId: $event->companyId,
            startsAt: $startsAt,
            endsAt: $endsAt
        );
    }
}
