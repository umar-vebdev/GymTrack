<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Listeners;

use App\Modules\Notifications\Application\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Domain\NotificationType;
use App\Modules\Sales\Domain\Events\MembershipRequestPaid;

final class SendMembershipRequestPaidNotificationListener
{
    public function handle(MembershipRequestPaid $event): void
    {
        SendPushNotificationJob::dispatch(
            companyId: $event->companyId,
            userId: $event->userId,
            clientId: $event->clientId,
            title: 'Абонемент активирован',
            body: 'Ваша заявка оплачена. Абонемент активирован, вы можете отметить посещение.',
            type: NotificationType::MembershipRequestPaid,
            recipientToken: null,
            entityType: 'membership_request',
            entityId: $event->requestId
        );
    }
}
