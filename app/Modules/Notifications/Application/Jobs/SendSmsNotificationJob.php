<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Jobs;

use App\Modules\Notifications\Application\NotificationSender;
use App\Modules\Notifications\Domain\NotificationLogRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class SendSmsNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $companyId,
        public readonly ?int $userId,
        public readonly ?int $clientId,
        public readonly string $phone,
        public readonly string $message,
        public readonly NotificationType $type,
        public readonly ?string $entityType = null,
        public readonly ?int $entityId = null
    ) {}

    public function handle(
        NotificationSender $sender,
        NotificationLogRepositoryInterface $logs
    ): void {
        if ($this->entityType !== null && $this->entityId !== null && $logs->alreadySent($this->type, $this->entityType, $this->entityId)) {
            Log::info("SMS skipped: already sent for {$this->entityType} #{$this->entityId}");

            return;
        }

        $sender->sendSms(
            companyId: $this->companyId,
            userId: $this->userId,
            clientId: $this->clientId,
            phone: $this->phone,
            message: $this->message,
            type: $this->type,
            entityType: $this->entityType,
            entityId: $this->entityId
        );
    }
}
