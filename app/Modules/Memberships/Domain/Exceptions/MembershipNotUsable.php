<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Exceptions;

use DomainException;

final class MembershipNotUsable extends DomainException
{
    public static function forStatus(int $membershipId, string $status): self
    {
        return new self(sprintf('Абонемент #%d недоступен для использования (статус: %s).', $membershipId, $status));
    }
}
