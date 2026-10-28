<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain;

use DateTimeImmutable;

interface SubscriptionRepositoryInterface
{
    public function findByCompanyId(int $companyId): ?Subscription;

    public function findByCompanyIdForUpdate(int $companyId): ?Subscription;

    public function createTrial(
        int $companyId,
        DateTimeImmutable $startsAt,
        DateTimeImmutable $endsAt
    ): Subscription;

    public function save(Subscription $subscription): void;

    /**
     * Возвращает подписки в статусах trial/active, у которых ends_at < $at.
     *
     * @return list<Subscription>
     */
    public function listDueForExpiry(DateTimeImmutable $at): array;
}
