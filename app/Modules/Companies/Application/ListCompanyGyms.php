<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\Gym;
use App\Modules\Companies\Domain\GymRepositoryInterface;

/**
 * Список филиалов (залов) компании (Бизнес-ТЗ 3, 4.1).
 */
final class ListCompanyGyms
{
    public function __construct(
        private readonly GymRepositoryInterface $gyms,
    ) {}

    /**
     * @return list<Gym>
     */
    public function execute(int $companyId): array
    {
        return $this->gyms->listByCompany($companyId);
    }
}
