<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\ClassBookingRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;

/**
 * Список записей на занятие расписания для сотрудников и тренеров зала (Бизнес-ТЗ 8; D-011).
 */
final class ListSessionBookings
{
    public function __construct(
        private readonly ClassSessionRepositoryInterface $sessions,
        private readonly ClassBookingRepositoryInterface $bookings,
    ) {}

    /**
     * @return list<ClassBooking>
     *
     * @throws ScheduleResourceNotFound занятие не найдено или принадлежит другой компании
     */
    public function execute(int $companyId, int $sessionId): array
    {
        if ($this->sessions->findById($companyId, $sessionId) === null) {
            throw ScheduleResourceNotFound::session($sessionId);
        }

        return $this->bookings->listForSession($companyId, $sessionId);
    }
}
