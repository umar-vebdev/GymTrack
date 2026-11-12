<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Contracts;

use DateTimeImmutable;

/**
 * Публичный контракт чтения расписания зала для модуля Catalog (Бизнес-ТЗ 7, 8; D-015).
 */
interface ScheduleCatalogQueryInterface
{
    /**
     * Возвращает предстоящие неотменённые занятия зала в интервале [max(from, now), to],
     * отсортированные по (starts_at, public_id).
     *
     * @return list<PublicClassSession>
     */
    public function listUpcomingForGym(
        int $gymId,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        DateTimeImmutable $now,
    ): array;
}

