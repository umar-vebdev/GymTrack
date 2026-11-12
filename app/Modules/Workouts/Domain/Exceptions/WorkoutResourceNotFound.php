<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain\Exceptions;

use DomainException;

final class WorkoutResourceNotFound extends DomainException
{
    public static function exercise(int $id): self
    {
        return new self(sprintf('Упражнение #%d не найдено.', $id));
    }
}
