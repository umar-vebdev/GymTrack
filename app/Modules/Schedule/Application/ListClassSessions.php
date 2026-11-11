<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Companies\Contracts\GymLookupInterface;
use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassSession;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use DateTimeImmutable;

/**
 * Список занятий расписания зала за выбранный интервал времени (Бизнес-ТЗ 8; D-011).
 */
final class ListClassSessions
{
    public function __construct(
        private readonly GymLookupInterface $gyms,
        private readonly ClassSessionRepositoryInterface $sessions,
    ) {}

    /**
     * @return list<ClassSession>
     *
     * @throws ScheduleResourceNotFound зал не найден или принадлежит другой компании
     * @throws InvalidClassSession конец интервала раньше начала
     */
    public function execute(
        int $companyId,
        int $gymId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array {
        if (!$this->gyms->existsInCompany($gymId, $companyId)) {
            throw ScheduleResourceNotFound::gym($gymId);
        }

        if ($from > $to) {
            throw InvalidClassSession::invalidTimeRange();
        }

        return $this->sessions->listForGymInPeriod($companyId, $gymId, $from, $to);
    }
}

