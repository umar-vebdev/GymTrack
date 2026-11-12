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

    public static function plan(int $id): self
    {
        return new self(sprintf('Тренировочный план #%d не найден.', $id));
    }

    public static function session(int $id): self
    {
        return new self(sprintf('Тренировочная сессия #%d не найдена.', $id));
    }
}
