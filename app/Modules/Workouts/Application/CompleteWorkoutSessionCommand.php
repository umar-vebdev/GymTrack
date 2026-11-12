<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Application;

final readonly class CompleteWorkoutSessionCommand
{
    public function __construct(
        public int $userId,
        public int $sessionId,
        public bool $cancel = false,
        public ?string $notes = null,
    ) {}
}

