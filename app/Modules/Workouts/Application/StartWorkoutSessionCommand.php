<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

final readonly class StartWorkoutSessionCommand
{
    public function __construct(
        public int $userId,
        public ?int $planId = null,
        public ?string $title = null,
    ) {}
}

