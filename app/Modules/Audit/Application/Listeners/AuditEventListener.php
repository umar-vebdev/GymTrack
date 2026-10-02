<?php

declare(strict_types=1);

namespace App\Modules\Audit\Application\Listeners;

use App\Modules\Audit\Domain\AuditRepositoryInterface;
use App\Shared\Domain\AuditableEvent;

final class AuditEventListener
{
    public function __construct(
        private readonly AuditRepositoryInterface $auditRepository
    ) {}

    public function handle(AuditableEvent $event): void
    {
        $this->auditRepository->create(
            userId: $event->userId(),
            companyId: $event->companyId(),
            action: $event->action(),
            entityType: $event->entityType(),
            entityId: $event->entityId(),
            payload: $event->payload(),
            ipAddress: $event->ipAddress()
        );
    }
}
