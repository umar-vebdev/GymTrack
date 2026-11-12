<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain\Exceptions;

use DomainException;

final class InvalidClassBooking extends DomainException
{
    public static function invalidIdentifiers(): self
    {
        return new self('Идентификаторы записи на занятие должны быть положительными.');
    }

    public static function publicIdRequired(): self
    {
        return new self('Публичный идентификатор записи на занятие обязателен.');
    }

    public static function idempotencyKeyTooLong(int $max): self
    {
        return new self(sprintf('Ключ идемпотентности не может быть длиннее %d символов.', $max));
    }

    public static function bookedCannotHaveCancelledAt(): self
    {
        return new self('Активная запись не может иметь дату отмены.');
    }

    public static function cancelledRequiresCancelledAt(): self
    {
        return new self('Отменённая запись должна содержать дату отмены.');
    }

    public static function cancelledBeforeBooked(): self
    {
        return new self('Время отмены записи не может быть раньше времени её создания.');
    }

    public static function alreadyCancelled(int $bookingId): self
    {
        return new self(sprintf('Запись на занятие #%d уже отменена.', $bookingId));
    }

    public static function alreadyBooked(int $sessionId): self
    {
        return new self(sprintf('Вы уже записаны на занятие #%d.', $sessionId));
    }

    public static function idempotencyKeyConflict(): self
    {
        return new self('Ключ идемпотентности уже использован для другой записи.');
    }
}
