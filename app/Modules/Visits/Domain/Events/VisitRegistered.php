<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Events;

use App\Modules\Visits\Domain\VisitMethod;
use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class VisitRegistered implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly int $visitId,
        private readonly int $companyId,
        private readonly int $gymId,
        private readonly int $clientId,
        private readonly int $membershipId,
        private readonly VisitMethod $method,
        private readonly DateTimeImmutable $visitedAt,
        private readonly ?int $registeredByUserId,
        private readonly ?string $device = null,
    ) {
        $this->occurredOn = new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function visitId(): int
    {
        return $this->visitId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function userId(): ?int
    {
        return $this->registeredByUserId;
    }

    public function action(): string
    {
        return 'VISIT_REGISTERED';
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
            'method' => $this->method->value,
            'device' => $this->device,
            'visited_at' => $this->visitedAt->format(DATE_ATOM),
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}
