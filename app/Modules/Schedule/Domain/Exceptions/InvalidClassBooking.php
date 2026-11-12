<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain\Exceptions;

use DomainException;

/**
 * Доменные ошибки инвариантов и бизнес-правил записи клиента на групповое занятие.
 */
final class InvalidClassBooking extends DomainException
{
    public const string CODE_REJECTED = 'CLASS_BOOKING_REJECTED';

    public const string CODE_CANCELLATION_NOT_ALLOWED = 'CLASS_BOOKING_CANCELLATION_NOT_ALLOWED';

    public const string CODE_NO_ACTIVE_MEMBERSHIP = 'NO_ACTIVE_MEMBERSHIP';

    public const string CODE_MEMBERSHIP_FROZEN = 'MEMBERSHIP_FROZEN';

    public const string CODE_OUTSIDE_ALLOWED_TIME = 'OUTSIDE_ALLOWED_TIME';

    private function __construct(
        string $message,
        public readonly string $errorCode = self::CODE_REJECTED,
    ) {
        parent::__construct($message);
    }

    public static function invalidIdentifiers(): self
    {
        return new self('Идентификаторы записи на занятие должны быть положительными.');
    }

    public static function invalidMembershipId(): self
    {
        return new self('Идентификатор абонемента должен быть положительным.');
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

    public static function cannotCancelStartedSession(int $sessionId): self
    {
        return new self(
            sprintf('Нельзя отменить запись на уже начавшееся занятие #%d.', $sessionId),
            self::CODE_CANCELLATION_NOT_ALLOWED,
        );
    }

    public static function noActiveMembership(): self
    {
        return new self(
            'Нет активного абонемента для записи на занятие.',
            self::CODE_NO_ACTIVE_MEMBERSHIP,
        );
    }

    public static function membershipFrozen(): self
    {
        return new self(
            'Абонемент заморожен.',
            self::CODE_MEMBERSHIP_FROZEN,
        );
    }

    public static function outsideAllowedTime(): self
    {
        return new self(
            'Время начала занятия не подходит по расписанию абонемента.',
            self::CODE_OUTSIDE_ALLOWED_TIME,
        );
    }
}
