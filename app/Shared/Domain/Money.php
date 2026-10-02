<?php

declare(strict_types=1);

namespace App\Shared\Domain;

use InvalidArgumentException;

final class Money
{
    public function __construct(
        private readonly int $amount,
        private readonly string $currency = 'RUB'
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        if (trim($currency) === '') {
            throw new InvalidArgumentException('Currency cannot be empty.');
        }
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency()) {
            throw new InvalidArgumentException('Cannot add different currencies.');
        }

        return new self($this->amount + $other->amount(), $this->currency);
    }
}
