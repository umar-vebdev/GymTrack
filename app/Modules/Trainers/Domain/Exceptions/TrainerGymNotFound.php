<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Domain\Exceptions;

use DomainException;

final class TrainerGymNotFound extends DomainException
{
    public static function withId(int $gymId): self
    {
        return new self(sprintf('Зал #%d не найден.', $gymId));
    }
}
