<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain;

use DateTimeImmutable;
use DomainException;

/**
 * Доменная сущность подписки компании на платформу GymTrack (Бизнес-ТЗ 1.1, 4.1, 12.8; Тех. ТЗ 3.6).
 */
final class Subscription
{
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private ?int $planId,
        private SubscriptionStatus $status,
        private DateTimeImmutable $startsAt,
        private DateTimeImmutable $endsAt
    ) {
        if ($endsAt < $startsAt) {
            throw new DomainException('Дата окончания подписки не может быть раньше даты начала.');
        }

        if ($status === SubscriptionStatus::Active && $planId === null) {
            throw new DomainException('Для активной подписки должен быть указан тарифный план.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function planId(): ?int
    {
        return $this->planId;
    }

    public function status(): SubscriptionStatus
    {
        return $this->status;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    /**
     * Проверяет, находится ли компания в режиме «только чтение» на момент $at (Бизнес-ТЗ 4.1, 12.8).
     */
    public function isReadOnlyAt(DateTimeImmutable $at): bool
    {
        return $this->status === SubscriptionStatus::Expired || $at > $this->endsAt;
    }

    /**
     * Переводит подписку в статус Expired, если срок истёк к моменту $at.
     * Возвращает true, если статус изменился (идемпотентно).
     */
    public function expireIfDue(DateTimeImmutable $at): bool
    {
        if ($this->status === SubscriptionStatus::Expired) {
            return false;
        }

        if ($at <= $this->endsAt) {
            return false;
        }

        $this->status = SubscriptionStatus::Expired;

        return true;
    }

    /**
     * Активирует или продлевает подписку компании по выбранному тарифному плану платформы.
     */
    public function activatePlan(PlatformPlan $plan, DateTimeImmutable $at): void
    {
        if (!$plan->isActive()) {
            throw new DomainException(sprintf('Тарифный план «%s» неактивен.', $plan->name()));
        }

        if ($this->status === SubscriptionStatus::Active && $this->endsAt > $at) {
            $this->endsAt = $plan->period()->extendDate($this->endsAt);
        } else {
            $this->startsAt = $at;
            $this->endsAt = $plan->period()->extendDate($at);
        }

        $this->planId = $plan->id();
        $this->status = SubscriptionStatus::Active;
    }
}

