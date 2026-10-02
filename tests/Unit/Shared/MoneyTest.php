<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Domain\Money;
use InvalidArgumentException;
use Tests\TestCase;

final class MoneyTest extends TestCase
{
    public function test_it_creates_money(): void
    {
        $money = new Money(1000, 'RUB');

        $this->assertSame(1000, $money->amount());
        $this->assertSame('RUB', $money->currency());
    }

    public function test_it_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative.');

        new Money(-100);
    }

    public function test_it_cannot_have_empty_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency cannot be empty.');

        new Money(100, '');
    }

    public function test_it_adds_money(): void
    {
        $money = new Money(1000, 'RUB');
        $other = new Money(500, 'RUB');

        $result = $money->add($other);

        $this->assertSame(1500, $result->amount());
        $this->assertSame('RUB', $result->currency());

        // Ensure immutability
        $this->assertSame(1000, $money->amount());
    }

    public function test_it_cannot_add_different_currencies(): void
    {
        $money = new Money(1000, 'RUB');
        $other = new Money(500, 'USD');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add different currencies.');

        $money->add($other);
    }
}
