<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Jobs;

use App\Modules\Notifications\Application\NotificationSender;
use App\Modules\Notifications\Domain\DeviceTokenRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationLogRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $companyId,
        public readonly ?int $userId,
        public readonly ?int $clientId,
        public readonly string $title,
        public readonly string $body,
        public readonly NotificationType $type,
        public readonly ?string $recipientToken = null,
        public readonly ?string $entityType = null,
        public readonly ?int $entityId = null,
        public readonly array $payload = []
    ) {}

    public function handle(
        NotificationSender $sender,
        DeviceTokenRepositoryInterface $deviceTokens,
        NotificationLogRepositoryInterface $logs
    ): void {
        if ($this->entityType !== null && $this->entityId !== null && $logs->alreadySent($this->type, $this->entityType, $this->entityId)) {
            Log::info("Push skipped: already sent for {$this->entityType} #{$this->entityId}");

            return;
        }

        if ($this->recipientToken !== null) {
            $sender->sendPush(
                companyId: $this->companyId,
                userId: $this->userId,
                clientId: $this->clientId,
                recipient: $this->recipientToken,
                title: $this->title,
                body: $this->body,
                type: $this->type,
                entityType: $this->entityType,
                entityId: $this->entityId,
                payload: $this->payload
            );

            return;
        }

        if ($this->userId === null) {
            Log::warning('Push skipped: neither recipientToken nor userId provided', [
                'company_id' => $this->companyId,
                'type' => $this->type->value,
            ]);

            return;
        }

        $tokens = $deviceTokens->findTokensForUser($this->userId);
        if ($tokens === []) {
            Log::info("Push skipped for user {$this->userId}: no device tokens registered");

            return;
        }

        foreach ($tokens as $token) {
            $sender->sendPush(
                companyId: $this->companyId,
                userId: $this->userId,
                clientId: $this->clientId,
                recipient: $token,
                title: $this->title,
                body: $this->body,
                type: $this->type,
                entityType: $this->entityType,
                entityId: $this->entityId,
                payload: $this->payload
            );
        }
    }
}
