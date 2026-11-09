<?php

declare(strict_types=1);

namespace App\Modules\Trainers\Domain\Exceptions;

use DomainException;

final class InvalidTrainerProfile extends DomainException
{
    public static function nameRequired(): self
    {
        return new self('Укажите имя тренера.');
    }

    public static function nameTooLong(int $max): self
    {
        return new self(sprintf('Имя тренера не может быть длиннее %d символов.', $max));
    }

    public static function bioTooLong(int $max): self
    {
        return new self(sprintf('Описание тренера не может быть длиннее %d символов.', $max));
    }
}
