<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Exceptions;

use DomainException;

final class NoVisitsLeft extends DomainException
{
    public static function forMembership(int $membershipId): self
    {
        return new self(sprintf('На абонементе #%d закончились занятия.', $membershipId));
    }
}
