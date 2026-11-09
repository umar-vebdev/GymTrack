<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Domain\Exceptions;

use DomainException;

final class TrainerNotFound extends DomainException
{
    public static function withId(int $trainerId): self
    {
        return new self(sprintf('Тренер #%d не найден.', $trainerId));
    }
}
