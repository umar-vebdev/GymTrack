<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure;

use App\Modules\Audit\Domain\AuditRepositoryInterface;
use App\Modules\Audit\Infrastructure\Persistence\AuditEntryModel;

final class EloquentAuditRepository implements AuditRepositoryInterface
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
    ): int {
        $model = AuditEntryModel::create([
            'user_id' => $userId,
            'company_id' => $companyId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'ip_address' => $ipAddress,
        ]);

        return $model->id;
    }
}
