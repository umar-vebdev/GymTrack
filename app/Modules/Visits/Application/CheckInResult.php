<?php

declare(strict_types=1);

namespace App\Modules\Visits\Application;

use App\Modules\Visits\Domain\Visit;

final class CheckInResult
{
    public function __construct(
        public readonly Visit $visit,
        public readonly ?int $remainingVisits,
    ) {}
}
