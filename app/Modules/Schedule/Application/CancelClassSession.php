<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Memberships\Contracts\MembershipVisitConsumerInterface;
use App\Modules\Schedule\Domain\ClassBookingRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSession;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSessionStatus;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Идемпотентная отмена занятия расписания залом с массовой отменой броней и возвратом посещений (Бизнес-ТЗ 8; D-011, D-014).
 */
final class CancelClassSession
{
    public function __construct(
        private readonly ClassSessionRepositoryInterface $sessions,
        private readonly ClassBookingRepositoryInterface $bookings,
        private readonly MembershipVisitConsumerInterface $memberships,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws ScheduleResourceNotFound занятие не найдено или принадлежит другой компании
     */
    public function execute(int $companyId, int $sessionId): ClassSession
    {
        $now = $this->clock->now();

        /** @var ClassSession $cancelled */
        $cancelled = DB::transaction(function () use ($companyId, $sessionId, $now): ClassSession {
            // Единый порядок блокировок для всего модуля: session -> booking -> membership
            $session = $this->sessions->findByIdForUpdate($companyId, $sessionId);
            if ($session === null) {
                throw ScheduleResourceNotFound::session($sessionId);
            }

            if ($session->status() === ClassSessionStatus::CANCELLED) {
                return $session;
            }

            $activeBookings = $this->bookings->listActiveForSessionForUpdate($companyId, $sessionId);

            foreach ($activeBookings as $booking) {
                $cancelAt = $now >= $booking->bookedAt() ? $now : $booking->bookedAt();
                $booking->cancel($cancelAt);
                $this->bookings->save($booking);
                $session->releaseSeat();

                if ($booking->membershipId() !== null) {
                    $this->memberships->restoreVisit($booking->membershipId(), $now);
                }
            }

            $session->cancel();
            $this->sessions->save($session);

            return $session;
        });

        return $cancelled;
    }
}
