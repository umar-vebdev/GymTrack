<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Infrastructure\SystemClock;
use DateTimeImmutable;
use Tests\TestCase;

final class SystemClockTest extends TestCase
{
    public function test_it_returns_current_time(): void
    {
        $clock = new SystemClock;

        $now = $clock->now();

        $this->assertInstanceOf(DateTimeImmutable::class, $now);
        // Ensure it's very close to current actual time
        $this->assertEqualsWithDelta(time(), $now->getTimestamp(), 2);
    }
}
