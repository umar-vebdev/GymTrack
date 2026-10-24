<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain\Exceptions;

use DomainException;

final class ClientNotAvailable extends DomainException
{
    public static function withId(int $clientId): self
    {
        return new self(sprintf('Клиент #%d не найден в этой компании.', $clientId));
    }
}
