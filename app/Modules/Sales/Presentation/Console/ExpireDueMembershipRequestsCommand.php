<?php

declare(strict_types=1);

namespace App\Modules\Sales\Presentation\Console;

use App\Modules\Sales\Application\ExpireDueMembershipRequests;
use Illuminate\Console\Command;

final class ExpireDueMembershipRequestsCommand extends Command
{
    protected $signature = 'sales:expire-requests';

    protected $description = 'Переводит просроченные заявки на абонемент со сроком жизни более 24 часов в статус expired';

    public function __construct(
        private readonly ExpireDueMembershipRequests $expireDueRequests
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $total = $this->expireDueRequests->execute();

        $this->info(sprintf('Отменено просроченных заявок: %d', $total));

        return self::SUCCESS;
    }
}
