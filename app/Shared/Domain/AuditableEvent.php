<?php

declare(strict_types=1);

namespace App\Shared\Domain;

interface AuditableEvent
{
    public function userId(): ?int;

    public function companyId(): int;

    public function action(): string;

    public function entityType(): string;

    public function entityId(): string;

    /**
     * @return array<string, mixed>|null
     */
    public function payload(): ?array;

    public function ipAddress(): ?string;
}
