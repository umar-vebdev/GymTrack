<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain\Exceptions;

use DomainException;

final class InvalidClassTemplate extends DomainException
{
    public static function titleRequired(): self
    {
        return new self('Укажите название группового занятия.');
    }

    public static function titleTooLong(int $max): self
    {
        return new self(sprintf('Название занятия не может быть длиннее %d символов.', $max));
    }

    public static function descriptionTooLong(int $max): self
    {
        return new self(sprintf('Описание занятия не может быть длиннее %d символов.', $max));
    }

    public static function invalidWeekday(int $weekday): self
    {
        return new self(sprintf('День недели должен быть в диапазоне 1..7 (передано: %d).', $weekday));
    }

    public static function invalidStartTimeMinute(int $minute): self
    {
        return new self(sprintf('Время начала в минутах должно быть в диапазоне 0..1439 (передано: %d).', $minute));
    }

    public static function invalidDurationMinutes(int $min, int $max): self
    {
        return new self(sprintf('Длительность занятия должна быть в диапазоне %d..%d минут.', $min, $max));
    }

    public static function invalidCapacity(int $min, int $max): self
    {
        return new self(sprintf('Лимит мест должен быть в диапазоне %d..%d.', $min, $max));
    }
}
