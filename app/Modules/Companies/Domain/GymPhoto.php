<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

use DateTimeImmutable;

final readonly class GymPhoto
{
    public function __construct(
        public int $id,
        public int $companyId,
        public int $gymId,
        public string $path,
        public int $order,
        public DateTimeImmutable $createdAt,
    ) {}
}
