<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Application;

final readonly class UpdateClassTemplateCommand
{
    public function __construct(
        public int $companyId,
        public int $templateId,
        public int $trainerId,
        public string $title,
        public ?string $description,
        public int $weekday,
        public int $startTimeMinute,
        public int $durationMinutes,
        public int $capacity,
        public ?bool $isActive = null,
    ) {}
}
