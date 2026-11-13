<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class StaffMemberAdded implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly int $staffId,
        public readonly int $companyId,
        public readonly int $targetUserId,
        public readonly string $role,
        public readonly int $actorUserId,
        ?DateTimeImmutable $occurredOn = null,
    ) {
        $this->occurredOn = $occurredOn ?? new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function userId(): int
    {
        return $this->actorUserId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function action(): string
    {
        return 'STAFF_MEMBER_ADDED';
    }

    public function entityType(): string
    {
        return 'StaffMember';
    }

    public function entityId(): string
    {
        return (string) $this->staffId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'target_user_id' => $this->targetUserId,
            'role' => $this->role,
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}

