<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Events;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class MembershipCancelled implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly int $membershipId,
        private readonly int $companyId,
        private readonly int $clientId,
        private readonly string $reason,
        private readonly ?int $cancelledByUserId
    ) {
        $this->occurredOn = new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function membershipId(): int
    {
        return $this->membershipId;
    }

    public function userId(): ?int
    {
        return $this->cancelledByUserId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function action(): string
    {
        return 'MEMBERSHIP_CANCELLED';
    }

    public function entityType(): string
    {
        return 'Membership';
    }

    public function entityId(): string
    {
        return (string) $this->membershipId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'client_id' => $this->clientId,
            'reason' => $this->reason,
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}
