<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Exceptions;

use DomainException;

final class ClientNotAvailable extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Клиент #{$id} не найден в этой компании.");
    }
}
