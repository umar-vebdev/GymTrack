<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Visits\Domain\Visit;
use App\Modules\Visits\Domain\VisitRepositoryInterface;

final class ListCompanyVisits
{
    public function __construct(
        private readonly VisitRepositoryInterface $visits,
    ) {}

    /**
     * @return list<Visit>
     */
    public function execute(int $companyId): array
    {
        return $this->visits->listByCompany($companyId);
    }
}
