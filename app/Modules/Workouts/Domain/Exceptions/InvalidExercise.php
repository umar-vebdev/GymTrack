<?php

declare(strict_types=1);

namespace App\Modules\Workouts\Domain\Exceptions;

use DomainException;

final class InvalidExercise extends DomainException
{
    public static function nameRequired(): self
    {
        return new self('Укажите название упражнения.');
    }

    public static function nameTooLong(int $max): self
    {
        return new self(sprintf('Название упражнения не может быть длиннее %d символов.', $max));
    }

    public static function descriptionTooLong(int $max): self
    {
        return new self(sprintf('Описание упражнения не может быть длиннее %d символов.', $max));
    }

    public static function invalidOwner(): self
    {
        return new self('Идентификатор владельца упражнения должен быть положительным числом.');
    }
}
