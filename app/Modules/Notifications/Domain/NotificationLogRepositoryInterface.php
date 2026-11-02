<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

use DateTimeImmutable;

interface NotificationLogRepositoryInterface
{
    public function create(NotificationLogEntry $entry): int;

    public function markSent(int $id, DateTimeImmutable $sentAt): void;

    public function markFailed(int $id, string $errorMessage): void;

    public function alreadySent(
        NotificationType $type,
        string $entityType,
        int $entityId,
        ?DateTimeImmutable $since = null
    ): bool;
}
