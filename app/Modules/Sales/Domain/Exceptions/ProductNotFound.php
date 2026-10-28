<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Exceptions;

use DomainException;

final class ProductNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Товар #%d не найден.', $id));
    }
}
