<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\ClassBookingRepositoryInterface;
use App\Modules\Schedule\Domain\ClassSessionRepositoryInterface;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassBooking;
use App\Modules\Schedule\Domain\Exceptions\InvalidClassSession;
use App\Modules\Schedule\Domain\Exceptions\ScheduleResourceNotFound;
use App\Shared\Domain\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Идемпотентная запись клиента на групповое занятие с блокировкой строки занятия (Бизнес-ТЗ 8; Тех. ТЗ 7.3; D-011, D-013).
 */
final class BookClassSession
{
    public function __construct(
        private readonly ClassSessionRepositoryInterface $sessions,
        private readonly ClassBookingRepositoryInterface $bookings,
        private readonly ClientLookupInterface $clients,
        private readonly Clock $clock,
    ) {}

    /**
     * @throws ScheduleResourceNotFound занятие не найдено
     * @throws InvalidClassSession занятие отменено, уже началось или нет свободных мест
     * @throws InvalidClassBooking клиент уже записан, ключ слишком длинный или использован для другого занятия
     */
    public function execute(BookClassSessionCommand $command): ClassBooking
    {
        $cleanKey = ClassBooking::cleanIdempotencyKey($command->idempotencyKey);

        $companyId = $this->sessions->findCompanyId($command->sessionId);
        if ($companyId === null) {
            throw ScheduleResourceNotFound::session($command->sessionId);
        }

        if ($cleanKey !== null) {
            $existingClientId = $this->clients->findIdByUser($command->userId, $companyId);
            if ($existingClientId !== null) {
                $existing = $this->findExistingIdempotentBooking($companyId, $existingClientId, $command->sessionId, $cleanKey);
                if ($existing !== null) {
                    return $existing;
                }
            }
        }

        $now = $this->clock->now();

        /** @var ClassBooking $booking */
        $booking = DB::transaction(function () use ($command, $companyId, $cleanKey, $now): ClassBooking {
            $session = $this->sessions->findByIdForUpdate($companyId, $command->sessionId);
            if ($session === null) {
                throw ScheduleResourceNotFound::session($command->sessionId);
            }

            $clientId = $this->clients->findOrCreateForUser($command->userId, $companyId);

            if ($cleanKey !== null) {
                $existing = $this->findExistingIdempotentBooking($companyId, $clientId, $session->id(), $cleanKey);
                if ($existing !== null) {
                    return $existing;
                }
            }

            $session->assertOpenForBooking($now);

            if ($this->bookings->findActiveForSessionAndClient($companyId, $session->id(), $clientId) !== null) {
                throw InvalidClassBooking::alreadyBooked($session->id());
            }

            $session->reserveSeat($now);
            $this->sessions->save($session);

            return $this->bookings->create(
                companyId: $companyId,
                gymId: $session->gymId(),
                sessionId: $session->id(),
                clientId: $clientId,
                bookedAt: $now,
                idempotencyKey: $cleanKey,
            );
        });

        return $booking;
    }

    private function findExistingIdempotentBooking(
        int $companyId,
        int $clientId,
        int $sessionId,
        string $cleanKey,
    ): ?ClassBooking {
        $existing = $this->bookings->findByIdempotencyKey($companyId, $clientId, $cleanKey);
        if ($existing === null) {
            return null;
        }

        if ($existing->sessionId() !== $sessionId) {
            throw InvalidClassBooking::idempotencyKeyConflict();
        }

        return $existing;
    }
}
