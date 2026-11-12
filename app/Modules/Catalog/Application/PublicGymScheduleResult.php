<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Companies\Contracts\PublicGymProfile;
use App\Modules\Schedule\Contracts\PublicClassSession;
use DateTimeImmutable;

/**
 * Результат сценария получения публичного расписания зала за период (Бизнес-ТЗ 7, 8; D-015).
 */
final class PublicGymScheduleResult
{
    /**
     * @param  list<PublicClassSession>  $sessions
     */
    public function __construct(
        public readonly PublicGymProfile $gym,
        public readonly DateTimeImmutable $fromDate,
        public readonly DateTimeImmutable $toDate,
        public readonly array $sessions,
    ) {}
}

