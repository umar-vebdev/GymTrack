<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\ClassBookingRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSessionStatus;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassBooking;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Идемпотентная отмена записи клиента на занятие с блокировкой строки занятия (Бизнес-ТЗ 8; D-011).
 */
final class CancelClassBooking
{
    public function __construct(
        private readonly ClassBookingRepositoryInterface $bookings,
        private readonly ClassSessionRepositoryInterface $sessions,
        private readonly ClientLookupInterface $clients,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws ScheduleResourceNotFound запись не найдена или принадлежит другому пользователю
     * @throws InvalidClassBooking нельзя отменить запись на уже начавшееся занятие
     */
    public function execute(int $userId, int $bookingId): ClassBooking
    {
        $booking = $this->bookings->findById($bookingId);
        if ($booking === null) {
            throw ScheduleResourceNotFound::booking($bookingId);
        }

        $clientIds = $this->clients->findAllClientIdsByUser($userId);
        if (!in_array($booking->clientId(), $clientIds, true)) {
            throw ScheduleResourceNotFound::booking($bookingId);
        }

        if ($booking->isCancelled()) {
            return $booking;
        }

        $now = $this->clock->now();
        $companyId = $booking->companyId();
        $sessionId = $booking->sessionId();

        /** @var ClassBooking $cancelled */
        $cancelled = DB::transaction(function () use ($companyId, $sessionId, $bookingId, $now): ClassBooking {
            // Порядок блокировки всегда session -> booking (совпадает с BookClassSession против взаимоблокировок)
            $session = $this->sessions->findByIdForUpdate($companyId, $sessionId);
            if ($session === null) {
                throw ScheduleResourceNotFound::session($sessionId);
            }

            $lockedBooking = $this->bookings->findByIdForUpdate($companyId, $bookingId);
            if ($lockedBooking === null) {
                throw ScheduleResourceNotFound::booking($bookingId);
            }

            if ($lockedBooking->isCancelled()) {
                return $lockedBooking;
            }

            if ($session->status() === ClassSessionStatus::SCHEDULED && $session->startsAt() <= $now) {
                throw InvalidClassBooking::cannotCancelStartedSession($session->id());
            }

            $lockedBooking->cancel($now);
            $session->releaseSeat();

            $this->bookings->save($lockedBooking);
            $this->sessions->save($session);

            return $lockedBooking;
        });

        return $cancelled;
    }
}
