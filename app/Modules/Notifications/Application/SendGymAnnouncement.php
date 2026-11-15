<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Notifications\Application\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Application\Jobs\SendSmsNotificationJob;
use App\Modules\Notifications\Domain\NotificationType;

/**
 * Сценарий массовой рассылки объявлений зала клиентам компании по push или SMS (Бизнес-ТЗ 5.3; Тех. ТЗ 9.4).
 */
final class SendGymAnnouncement
{
    public function __construct(
        private readonly ClientLookupInterface $clients,
    ) {}

    public function execute(int $companyId, string $channel, string $title, string $body): int
    {
        $normalizedTitle = trim($title);
        $normalizedBody = trim($body);
        $recipients = $this->clients->listRecipientsForCompany($companyId);
        $queued = 0;

        if ($channel === 'push') {
            foreach ($recipients as $recipient) {
                if ($recipient->userId === null) {
                    continue;
                }

                SendPushNotificationJob::dispatch(
                    companyId: $companyId,
                    userId: $recipient->userId,
                    clientId: $recipient->clientId,
                    title: $normalizedTitle,
                    body: $normalizedBody,
                    type: NotificationType::GymAnnouncement,
                );
                $queued++;
            }

            return $queued;
        }

        foreach ($recipients as $recipient) {
            if (trim($recipient->phone) === '') {
                continue;
            }

            SendSmsNotificationJob::dispatch(
                companyId: $companyId,
                userId: $recipient->userId,
                clientId: $recipient->clientId,
                phone: $recipient->phone,
                message: $normalizedTitle.': '.$normalizedBody,
                type: NotificationType::GymAnnouncement,
            );
            $queued++;
        }

        return $queued;
    }
}
