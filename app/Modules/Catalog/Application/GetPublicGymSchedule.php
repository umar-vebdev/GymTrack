<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Companies\Contracts\PublicGymQueryInterface;
use App\Modules\Schedule\Contracts\ScheduleCatalogQueryInterface;
use App\Shared\Domain\Clock;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Сценарий получения публичного расписания зала по ULID через контракты Companies и Schedule (D-015).
 */
final class GetPublicGymSchedule
{
    public const DEFAULT_PERIOD_DAYS = 7;

    public const MAX_PERIOD_DAYS = 62;

    public function __construct(
        private readonly PublicGymQueryInterface $gyms,
        private readonly ScheduleCatalogQueryInterface $schedule,
        private readonly Clock $clock,
    ) {}

    public function execute(
        string $publicId,
        ?DateTimeImmutable $fromDate = null,
        ?DateTimeImmutable $toDate = null,
    ): ?PublicGymScheduleResult {
        $utc = new DateTimeZone('UTC');
        $now = $this->clock->now()->setTimezone($utc);

        [$startDay, $endDay] = $this->resolvePeriod($now, $fromDate, $toDate, $utc);

        $gym = $this->gyms->findByPublicId($publicId);
        if ($gym === null) {
            return null;
        }

        $sessions = $this->schedule->listUpcomingForGym(
            gymId: $gym->gymId,
            from: $startDay->setTime(0, 0, 0),
            to: $endDay->setTime(23, 59, 59),
            now: $now,
        );

        return new PublicGymScheduleResult(
            gym: $gym,
            fromDate: $startDay,
            toDate: $endDay,
            sessions: $sessions,
        );
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function resolvePeriod(
        DateTimeImmutable $now,
        ?DateTimeImmutable $fromDate,
        ?DateTimeImmutable $toDate,
        DateTimeZone $utc,
    ): array {
        $startDay = ($fromDate ?? $now)->setTimezone($utc)->setTime(0, 0, 0);
        $endDay = $toDate !== null
            ? $toDate->setTimezone($utc)->setTime(0, 0, 0)
            : $startDay->modify('+'.(self::DEFAULT_PERIOD_DAYS - 1).' days');

        if ($startDay > $endDay) {
            throw new InvalidArgumentException('Дата окончания периода не может быть раньше даты начала.');
        }

        $daysCount = (int) $startDay->diff($endDay)->days + 1;
        if ($daysCount > self::MAX_PERIOD_DAYS) {
            throw new InvalidArgumentException(
                sprintf('Период расписания не может превышать %d дн.', self::MAX_PERIOD_DAYS),
            );
        }

        return [$startDay, $endDay];
    }
}
