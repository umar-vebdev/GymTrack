<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Domain\CompanyId;
use InvalidArgumentException;
use Tests\TestCase;

final class CompanyIdTest extends TestCase
{
    public function test_it_creates_company_id(): void
    {
        $id = new CompanyId('123e4567-e89b-12d3-a456-426614174000');

        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $id->toString());
    }

    public function test_it_cannot_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CompanyId cannot be empty.');

        new CompanyId('   ');
    }

    public function test_it_compares_equality(): void
    {
        $id1 = new CompanyId('123');
        $id2 = new CompanyId('123');
        $id3 = new CompanyId('456');

        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }
}
