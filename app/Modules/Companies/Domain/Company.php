<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

final class Company
{
    public function __construct(
        private readonly int $id,
        private readonly int $ownerId,
        private readonly string $name,
        private readonly int $currencyId = 1
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

    public function currencyId(): int
    {
        return $this->currencyId;
    }
}
