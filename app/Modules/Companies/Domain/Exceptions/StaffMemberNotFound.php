<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain\Exceptions;

use DomainException;

final class StaffMemberNotFound extends DomainException
{
    public static function withId(int $staffId): self
    {
        return new self(sprintf('Сотрудник #%d не найден.', $staffId));
    }
}

