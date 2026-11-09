<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Schedule\Domain\ClassTemplate;
use App\Modules\Schedule\Domain\ClassTemplateRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;

final class ListClassTemplates
{
    public function __construct(
        private readonly GymLookupInterface $gyms,
        private readonly ClassTemplateRepositoryInterface $templates,
    ) {}

    /**
     * @return list<ClassTemplate>
     *
     * @throws ScheduleResourceNotFound зал не существует или принадлежит другой компании
     */
    public function execute(int $companyId, int $gymId, ?bool $onlyActive = null): array
    {
        if (!$this->gyms->existsInCompany($gymId, $companyId)) {
            throw ScheduleResourceNotFound::gym($gymId);
        }

        return $this->templates->listForGym($companyId, $gymId, $onlyActive);
    }
}
