<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Events;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class MembershipIssued implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly int $membershipId,
        private readonly int $companyId,
        private readonly int $clientId,
        private readonly int $typeId,
        private readonly ?int $issuedByUserId
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
        return $this->issuedByUserId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function action(): string
    {
        return 'MEMBERSHIP_ISSUED';
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
        return ['client_id' => $this->clientId, 'type_id' => $this->typeId];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}
