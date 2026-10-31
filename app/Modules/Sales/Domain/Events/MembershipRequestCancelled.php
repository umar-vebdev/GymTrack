<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Events;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class MembershipRequestCancelled implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly int $requestId,
        public readonly int $companyId,
        public readonly int $gymId,
        public readonly int $userId,
        public readonly ?int $clientId,
        public readonly int $membershipTypeId,
        public readonly ?int $cancelledByUserId = null,
        public readonly ?string $reason = null
    ) {
        $this->occurredOn = new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
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
        return 'MEMBERSHIP_REQUEST_CANCELLED';
    }

    public function entityType(): string
    {
        return 'MembershipRequest';
    }

    public function entityId(): string
    {
        return (string) $this->requestId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'gym_id' => $this->gymId,
            'user_id' => $this->userId,
            'client_id' => $this->clientId,
            'membership_type_id' => $this->membershipTypeId,
            'cancelled_by_user_id' => $this->cancelledByUserId,
            'reason' => $this->reason,
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}

