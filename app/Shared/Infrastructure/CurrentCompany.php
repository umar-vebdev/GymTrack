<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Application\CurrentCompanyInterface;
use RuntimeException;

final class CurrentCompany implements CurrentCompanyInterface
{
    private ?int $id = null;

    public function id(): int
    {
        if ($this->id === null) {
            throw new RuntimeException('Current company is not set.');
        }

        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function hasId(): bool
    {
        return $this->id !== null;
    }
}
