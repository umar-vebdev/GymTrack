<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;

final class CompanyId
{
    public function __construct(private readonly string $id)
    {
        if (trim($id) === '') {
            throw new InvalidArgumentException('CompanyId cannot be empty.');
        }
    }

    public function toString(): string
    {
        return $this->id;
    }

    public function equals(CompanyId $other): bool
    {
        return $this->id === $other->toString();
    }
}
