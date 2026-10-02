<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

interface AuditRepositoryInterface
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function create(
        ?int $userId,
        int $companyId,
        string $action,
        string $entityType,
        string $entityId,
        ?array $payload,
        ?string $ipAddress
    ): int;
}
