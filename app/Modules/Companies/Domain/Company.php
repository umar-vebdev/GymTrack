<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

final class Company
{
    public function __construct(
        private readonly int $id,
        private readonly int $ownerId,
        private readonly string $name
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function ownerId(): int
    {
        return $this->ownerId;
    }

    public function name(): string
    {
        return $this->name;
    }
}
