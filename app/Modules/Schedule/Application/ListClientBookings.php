<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

use App\Modules\Clients\Contracts\ClientLookupInterface;
use App\Modules\Schedule\Domain\ClassBooking;
use App\Modules\Schedule\Domain\ClassBookingRepositoryInterface;
use App\Modules\Schedule\Domain\ClassBookingStatus;

/**
 * Список записей текущего клиента на групповые занятия во всех залах (Бизнес-ТЗ 8; D-011).
 */
final class ListClientBookings
{
    public function __construct(
        private readonly ClientLookupInterface $clients,
        private readonly ClassBookingRepositoryInterface $bookings,
    ) {}

    /**
     * @return array{items: list<ClassBooking>, total: int}
     */
    public function execute(
        int $userId,
        ?int $companyId = null,
        ?ClassBookingStatus $status = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $clientIds = $this->clients->findAllClientIdsByUser($userId);

        return $this->bookings->listForClient($clientIds, $companyId, $status, $page, $perPage);
    }
}
