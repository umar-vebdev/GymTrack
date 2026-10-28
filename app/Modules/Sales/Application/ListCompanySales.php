<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Domain\Sale;
use App\Modules\Sales\Domain\SaleRepositoryInterface;

final class ListCompanySales
{
    public function __construct(
        private readonly SaleRepositoryInterface $sales
    ) {}

    /**
     * @return list<Sale>
     */
    public function execute(int $companyId): array
    {
        return $this->sales->listByCompany($companyId);
    }
}
