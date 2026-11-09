<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Application;

final readonly class CreateTrainerCommand
{
    public function __construct(
        public int $companyId,
        public int $gymId,
        public string $name,
        public ?string $bio = null,
    ) {}
}
