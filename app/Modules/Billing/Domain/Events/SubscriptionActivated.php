<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Events;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/**
 * Аудируемое доменное событие: подтверждение оплаты и активация подписки компании на платформу (Бизнес-ТЗ 1.1; Тех. ТЗ 8).
 */
final class SubscriptionActivated implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly int $subscriptionId,
        private readonly int $companyId,
        private readonly int $planId,
        private readonly DateTimeImmutable $endsAt,
        private readonly int $activatedByUserId
    ) {
        $this->occurredOn = new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function userId(): int
    {
        return $this->activatedByUserId;
    }

    public function action(): string
    {
        return 'SUBSCRIPTION_ACTIVATED';
    }

    public function entityType(): string
    {
        return 'Subscription';
    }

    public function entityId(): string
    {
        return (string) $this->subscriptionId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'status' => 'active',
            'plan_id' => $this->planId,
            'ends_at' => $this->endsAt->format(DATE_ATOM),
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}

