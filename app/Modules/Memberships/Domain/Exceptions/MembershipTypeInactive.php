<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Exceptions;

use DomainException;

final class MembershipTypeInactive extends DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Тип абонемента #%d отключён и недоступен для выдачи.', $id));
    }
}
