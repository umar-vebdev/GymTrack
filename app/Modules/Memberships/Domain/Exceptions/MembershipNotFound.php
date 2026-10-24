<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Exceptions;

use DomainException;

final class MembershipNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Абонемент #%d не найден.', $id));
    }
}

