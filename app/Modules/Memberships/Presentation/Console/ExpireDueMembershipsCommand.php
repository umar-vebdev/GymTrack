<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Presentation\Console;

use App\Modules\Memberships\Application\ExpireDueMemberships;
use Illuminate\Console\Command;

final class ExpireDueMembershipsCommand extends Command
{
    protected $signature = 'memberships:expire-due {--company= : Идентификатор компании}';

    protected $description = 'Завершает абонементы, у которых истёк срок действия';

    public function __construct(
        private readonly ExpireDueMemberships $expireDueMemberships
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $companyOption = $this->option('company');

        $companyIds = is_string($companyOption) && $companyOption !== ''
            ? [(int) $companyOption]
            : $this->expireDueMemberships->companyIdsWithDueMemberships();

        $total = 0;

        foreach ($companyIds as $companyId) {
            $total += $this->expireDueMemberships->execute($companyId);
        }

        $this->info(sprintf('Завершено истёкших абонементов: %d', $total));

        return self::SUCCESS;
    }
}
