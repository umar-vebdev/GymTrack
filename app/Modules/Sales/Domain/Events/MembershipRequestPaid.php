<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Events;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class MembershipRequestPaid implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly int $requestId,
        public readonly int $companyId,
        public readonly int $gymId,
        public readonly int $userId,
        public readonly int $clientId,
        public readonly int $membershipTypeId,
        public readonly int $saleId,
        public readonly int $membershipId,
        public readonly int $acceptedByUserId,
        public readonly DateTimeImmutable $paidAt
    ) {
        $this->occurredOn = new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function userId(): int
    {
        return $this->acceptedByUserId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function action(): string
    {
        return 'MEMBERSHIP_REQUEST_PAID';
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
            'sale_id' => $this->saleId,
            'membership_id' => $this->membershipId,
            'paid_at' => $this->paidAt->format(DateTimeImmutable::ATOM),
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}
