<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Events;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class MembershipRequestCreated implements DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly int $requestId,
        public readonly int $companyId,
        public readonly int $gymId,
        public readonly int $userId,
        public readonly ?int $clientId,
        public readonly int $membershipTypeId,
        public readonly DateTimeImmutable $expiresAt
    ) {
        $this->occurredOn = new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
