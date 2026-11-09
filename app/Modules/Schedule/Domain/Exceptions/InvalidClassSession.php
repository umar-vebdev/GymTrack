<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain\Exceptions;

use DomainException;

final class InvalidClassSession extends DomainException
{
    public static function invalidTimeRange(): self
    {
        return new self('Время окончания занятия должно быть позже времени начала.');
    }

    public static function invalidBookedCount(int $bookedCount, int $capacity): self
    {
        return new self(sprintf(
            'Количество записанных клиентов (%d) должно быть в диапазоне 0..%d.',
            $bookedCount,
            $capacity,
        ));
    }

    public static function cancelled(int $sessionId): self
    {
        return new self(sprintf('Занятие #%d отменено.', $sessionId));
    }

    public static function noSeatsLeft(int $sessionId): self
    {
        return new self(sprintf('На занятии #%d нет свободных мест.', $sessionId));
    }
}
