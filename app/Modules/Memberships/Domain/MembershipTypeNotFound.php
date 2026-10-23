<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

use DomainException;

final class MembershipTypeNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Тип абонемента #%d не найден.', $id));
    }
}
