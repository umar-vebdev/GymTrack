<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

use App\Modules\Workouts\Domain\PlanLevel;

final readonly class CreateClientPlanCommand
{
    /**
     * @param  list<PlanItemInput>  $items
     */
    public function __construct(
        public int $userId,
        public string $title,
        public ?string $description,
        public PlanLevel $level,
        public array $items = [],
    ) {}
}
