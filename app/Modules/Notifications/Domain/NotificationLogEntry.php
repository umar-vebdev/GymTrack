<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

use DateTimeImmutable;

final readonly class NotificationLogEntry
{
    public function __construct(
        public int $companyId,
        public ?int $userId,
        public ?int $clientId,
        public NotificationChannel $channel,
        public NotificationType $type,
        public string $recipient,
        public ?string $title,
        public string $body,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public NotificationStatus $status = NotificationStatus::Pending,
        public ?string $error = null,
        public ?DateTimeImmutable $sentAt = null,
        public ?int $id = null
    ) {}
}
