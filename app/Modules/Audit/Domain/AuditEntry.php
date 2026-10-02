<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

final class AuditEntry
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        private readonly int $id,
        private readonly ?int $userId,
        private readonly int $companyId,
        private readonly string $action,
        private readonly string $entityType,
        private readonly string $entityId,
        private readonly ?array $payload,
        private readonly ?string $ipAddress,
        private readonly \DateTimeImmutable $createdAt
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function entityType(): string
    {
        return $this->entityType;
    }

    public function entityId(): string
    {
        return $this->entityId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(): ?array
    {
        return $this->payload;
    }

    public function ipAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
