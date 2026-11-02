<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Notifications\Application\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Application\Jobs\SendSmsNotificationJob;
use App\Modules\Notifications\Domain\NotificationType;
use App\Modules\Visits\Domain\Events\VisitRegistered;

final class SendVisitNotificationListener
{
    public function __construct(
        private readonly ClientLookupInterface $clientLookup
    ) {}

    public function handle(VisitRegistered $event): void
    {
        $recipient = $this->clientLookup->findRecipient($event->clientId());
        if ($recipient === null) {
            return;
        }

        if ($recipient->userId !== null) {
            SendPushNotificationJob::dispatch(
                companyId: $event->companyId(),
                userId: $recipient->userId,
                clientId: $recipient->clientId,
                title: 'Посещение подтверждено',
                body: 'Ваш визит в зал успешно зарегистрирован.',
                type: NotificationType::VisitRegistered,
                recipientToken: null,
                entityType: 'visit',
                entityId: $event->visitId()
            );

            return;
        }

        if ($recipient->phone !== '') {
            SendSmsNotificationJob::dispatch(
                companyId: $event->companyId(),
                userId: null,
                clientId: $recipient->clientId,
                phone: $recipient->phone,
                message: 'Ваш визит в зал успешно зарегистрирован.',
                type: NotificationType::VisitRegistered,
                entityType: 'visit',
                entityId: $event->visitId()
            );
        }
    }
}
