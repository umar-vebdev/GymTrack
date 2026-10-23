<?php

declare(strict_types=1);

namespace App\Modules\Clients\Domain;

use DomainException;

final class ClientNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Клиент #%d не найден.', $id));
    }
}
