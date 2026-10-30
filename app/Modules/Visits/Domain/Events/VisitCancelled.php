<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Events;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class VisitCancelled implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly int $visitId,
        private readonly int $companyId,
        private readonly int $gymId,
        private readonly int $clientId,
        private readonly int $membershipId,
        private readonly string $reason,
        private readonly int $cancelledByUserId,
        private readonly DateTimeImmutable $cancelledAt,
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
        return $this->cancelledByUserId;
    }

    public function action(): string
    {
        return 'VISIT_CANCELLED';
    }

    public function entityType(): string
    {
        return 'Visit';
    }

    public function entityId(): string
    {
        return (string) $this->visitId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'gym_id' => $this->gymId,
            'client_id' => $this->clientId,
            'membership_id' => $this->membershipId,
            'reason' => $this->reason,
            'cancelled_at' => $this->cancelledAt->format(DATE_ATOM),
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}
