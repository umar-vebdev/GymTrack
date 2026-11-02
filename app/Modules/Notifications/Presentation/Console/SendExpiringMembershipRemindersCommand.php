<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Console;

use App\Modules\Notifications\Application\SendExpiringMembershipReminders;
use Illuminate\Console\Command;

final class SendExpiringMembershipRemindersCommand extends Command
{
    protected $signature = 'notifications:send-expiring-reminders';

    protected $description = 'Ежедневная отправка напоминаний клиентам об окончании абонемента за 7 и 1 день';

    public function __construct(
        private readonly SendExpiringMembershipReminders $reminders
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $total = $this->reminders->execute();

        $this->info(sprintf('Отправлено напоминаний об окончании абонементов: %d', $total));

        return self::SUCCESS;
    }
}

