<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure;

use App\Modules\Notifications\Domain\NotificationLogEntry;
use App\Modules\Notifications\Domain\NotificationLogRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Domain\NotificationType;
use App\Modules\Notifications\Infrastructure\Persistence\NotificationLogModel;
use DateTimeImmutable;

final class EloquentNotificationLogRepository implements NotificationLogRepositoryInterface
{
    public function create(NotificationLogEntry $entry): int
    {
        $model = NotificationLogModel::query()->create([
            'company_id' => $entry->companyId,
            'user_id' => $entry->userId,
            'client_id' => $entry->clientId,
            'channel' => $entry->channel->value,
            'type' => $entry->type->value,
            'recipient' => $entry->recipient,
            'title' => $entry->title,
            'body' => $entry->body,
            'entity_type' => $entry->entityType,
            'entity_id' => $entry->entityId,
            'status' => $entry->status->value,
            'error' => $entry->error,
            'sent_at' => $entry->sentAt?->format('Y-m-d H:i:s'),
        ]);

        return $model->id;
    }

    public function markSent(int $id, DateTimeImmutable $sentAt): void
    {
        NotificationLogModel::query()
            ->where('id', $id)
            ->update([
                'status' => NotificationStatus::Sent->value,
                'sent_at' => $sentAt->format('Y-m-d H:i:s'),
                'error' => null,
            ]);
    }

    public function markFailed(int $id, string $errorMessage): void
    {
        NotificationLogModel::query()
            ->where('id', $id)
            ->update([
                'status' => NotificationStatus::Failed->value,
                'error' => $errorMessage,
            ]);
    }

    public function alreadySent(
        NotificationType $type,
        string $entityType,
        int $entityId,
        ?DateTimeImmutable $since = null
    ): bool {
        $query = NotificationLogModel::query()
            ->where('type', $type->value)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', NotificationStatus::Sent->value);

        if ($since !== null) {
            $query->where('sent_at', '>=', $since->format('Y-m-d H:i:s'));
        }

        return $query->exists();
    }
}
