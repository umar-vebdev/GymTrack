<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Notifications\Domain\NotificationChannel;
use App\Modules\Notifications\Domain\NotificationLogEntry;
use App\Modules\Notifications\Domain\NotificationLogRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Domain\NotificationType;
use App\Modules\Notifications\Domain\PushGatewayInterface;
use App\Modules\Notifications\Domain\SmsGatewayInterface;
use App\Shared\Domain\Clock;
use Throwable;

final class NotificationSender
{
    public function __construct(
        private readonly PushGatewayInterface $pushGateway,
        private readonly SmsGatewayInterface $smsGateway,
        private readonly NotificationLogRepositoryInterface $logs,
        private readonly Clock $clock
    ) {}

    public function sendSms(
        int $companyId,
        ?int $userId,
        ?int $clientId,
        string $phone,
        string $message,
        NotificationType $type,
        ?string $entityType = null,
        ?int $entityId = null
    ): int {
        $entry = new NotificationLogEntry(
            companyId: $companyId,
            userId: $userId,
            clientId: $clientId,
            channel: NotificationChannel::Sms,
            type: $type,
            recipient: $phone,
            title: null,
            body: $message,
            entityType: $entityType,
            entityId: $entityId,
            status: NotificationStatus::Pending
        );

        $logId = $this->logs->create($entry);

        try {
            $this->smsGateway->send($phone, $message);
            $this->logs->markSent($logId, $this->clock->now());
        } catch (Throwable $e) {
            $this->logs->markFailed($logId, $e->getMessage());
            throw $e;
        }

        return $logId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendPush(
        int $companyId,
        ?int $userId,
        ?int $clientId,
        string $recipient,
        string $title,
        string $body,
        NotificationType $type,
        ?string $entityType = null,
        ?int $entityId = null,
        array $payload = []
    ): int {
        $entry = new NotificationLogEntry(
            companyId: $companyId,
            userId: $userId,
            clientId: $clientId,
            channel: NotificationChannel::Push,
            type: $type,
            recipient: $recipient,
            title: $title,
            body: $body,
            entityType: $entityType,
            entityId: $entityId,
            status: NotificationStatus::Pending
        );

        $logId = $this->logs->create($entry);

        try {
            $this->pushGateway->send($recipient, $title, $body, $payload);
            $this->logs->markSent($logId, $this->clock->now());
        } catch (Throwable $e) {
            $this->logs->markFailed($logId, $e->getMessage());
            throw $e;
        }

        return $logId;
    }
}
