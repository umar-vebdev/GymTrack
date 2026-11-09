<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

final readonly class CreateClassTemplateCommand
{
    public function __construct(
        public int $companyId,
        public int $gymId,
        public int $trainerId,
        public string $title,
        public ?string $description,
        public int $weekday,
        public int $startTimeMinute,
        public int $durationMinutes,
        public int $capacity,
    ) {}
}
