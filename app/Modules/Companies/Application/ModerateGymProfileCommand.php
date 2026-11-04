<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

final readonly class ModerateGymProfileCommand
{
    public function __construct(
        public int $gymId,
        public string $decision,
        public ?string $reason,
        public int $moderatorUserId,
    ) {}
}
