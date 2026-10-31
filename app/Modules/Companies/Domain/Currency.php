<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

final class Currency
{
    public function __construct(
        private readonly int $id,
        private readonly string $code,
        private readonly string $name,
        private readonly ?string $symbol = null
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function symbol(): ?string
    {
        return $this->symbol;
    }
}

