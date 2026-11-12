<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\PlanLevel;

final readonly class CreateLibraryPlanCommand
{
    /**
     * @param  list<PlanItemInput>  $items
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public PlanLevel $level,
        public bool $isPublished = false,
        public array $items = [],
    ) {}
}

