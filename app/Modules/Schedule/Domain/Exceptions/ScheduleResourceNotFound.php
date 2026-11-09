<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain\Exceptions;

use DomainException;

final class ScheduleResourceNotFound extends DomainException
{
    public static function gym(int $gymId): self
    {
        return new self(sprintf('Зал #%d не найден.', $gymId));
    }

    public static function activeTrainerInGym(int $trainerId, int $gymId): self
    {
        return new self(sprintf('Активный тренер #%d не найден в зале #%d.', $trainerId, $gymId));
    }

    public static function template(int $templateId): self
    {
        return new self(sprintf('Шаблон занятия #%d не найден.', $templateId));
    }
}
