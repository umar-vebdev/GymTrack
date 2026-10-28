<?php

declare(strict_types=1);

namespace App\Modules\Billing\Presentation\Console;

use App\Modules\Billing\Application\ExpireDueSubscriptions;
use Illuminate\Console\Command;

final class ExpireDueSubscriptionsCommand extends Command
{
    protected $signature = 'billing:expire-due {--company= : Идентификатор компании}';

    protected $description = 'Переводит подписки залов и пробные периоды с истёкшим сроком в статус expired';

    public function __construct(
        private readonly ExpireDueSubscriptions $expireDueSubscriptions
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $companyOption = $this->option('company');

        $companyId = is_string($companyOption) && $companyOption !== ''
            ? (int) $companyOption
            : null;

        $total = $this->expireDueSubscriptions->execute($companyId);

        $this->info(sprintf('Переведено истёкших подписок в expired: %d', $total));

        return self::SUCCESS;
    }
}

