<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Memberships\Contracts\MembershipLookupInterface;
use App\Modules\Notifications\Application\Jobs\SendPushNotificationJob;
use App\Modules\Notifications\Application\Jobs\SendSmsNotificationJob;
use App\Modules\Notifications\Domain\NotificationLogRepositoryInterface;
use App\Modules\Notifications\Domain\NotificationType;
use App\Shared\Domain\Clock;
use DateTimeImmutable;

final class SendExpiringMembershipReminders
{
    public function __construct(
        private readonly MembershipLookupInterface $memberships,
        private readonly ClientLookupInterface $clients,
        private readonly NotificationLogRepositoryInterface $logs,
        private readonly Clock $clock
    ) {}

    public function execute(): int
    {
        $now = $this->clock->now();
        $total = 0;

        // 1. Напоминание за 7 дней
        $total += $this->sendForDaysAhead(7, NotificationType::MembershipExpiring7Days, $now);

        // 2. Напоминание за 1 день
        $total += $this->sendForDaysAhead(1, NotificationType::MembershipExpiring1Day, $now);

        return $total;
    }

    private function sendForDaysAhead(int $daysAhead, NotificationType $type, DateTimeImmutable $now): int
    {
        $targetDate = $now->modify(sprintf('+%d days', $daysAhead));
        $from = $targetDate->setTime(0, 0, 0);
        $to = $targetDate->setTime(23, 59, 59);

        $expiring = $this->memberships->findExpiringBetween($from, $to);
        $count = 0;

        foreach ($expiring as $item) {
            if ($this->logs->alreadySent($type, 'membership', $item->membershipId)) {
                continue;
            }

            $recipient = $this->clients->findRecipient($item->clientId);
            if ($recipient === null) {
                continue;
            }

            $daysText = $daysAhead === 1 ? '1 день' : sprintf('%d дней', $daysAhead);
            $title = 'Окончание абонемента';
            $message = sprintf('Напоминаем: срок действия вашего абонемента истекает через %s.', $daysText);

            if ($recipient->userId !== null) {
                SendPushNotificationJob::dispatch(
                    companyId: $item->companyId,
                    userId: $recipient->userId,
                    clientId: $item->clientId,
                    title: $title,
                    body: $message,
                    type: $type,
                    recipientToken: null,
                    entityType: 'membership',
                    entityId: $item->membershipId
                );

                $count++;
            } elseif ($recipient->phone !== '') {
                SendSmsNotificationJob::dispatch(
                    companyId: $item->companyId,
                    userId: null,
                    clientId: $item->clientId,
                    phone: $recipient->phone,
                    message: $message,
                    type: $type,
                    entityType: 'membership',
                    entityId: $item->membershipId
                );

                $count++;
            }
        }

        return $count;
    }
}
