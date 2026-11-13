<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final class StaffMemberRoleChanged implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly int $staffId,
        public readonly int $companyId,
        public readonly int $targetUserId,
        public readonly string $oldRole,
        public readonly string $newRole,
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
        return 'STAFF_MEMBER_ROLE_CHANGED';
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
            'old_role' => $this->oldRole,
            'new_role' => $this->newRole,
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}

