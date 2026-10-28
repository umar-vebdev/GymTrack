<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Exceptions;

use DomainException;

final class PlanNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Тарифный план #{$id} не найден.");
    }
}

