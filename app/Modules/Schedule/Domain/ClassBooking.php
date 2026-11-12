<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain;

use App\Modules\Schedule\Domain\Exceptions\InvalidClassBooking;
use DateTimeImmutable;

/**
 * Запись клиента на конкретное групповое занятие расписания (Бизнес-ТЗ 8; D-011).
 * Все метки времени хранятся в UTC; инварианты статуса и отмены защищены самой сущностью.
 */
final class ClassBooking
{
    public const IDEMPOTENCY_KEY_MAX = 128;

    private readonly string $publicId;

    private readonly ?string $idempotencyKey;

    public function __construct(
        private readonly int $id,
        string $publicId,
        private readonly int $companyId,
        private readonly int $gymId,
        private readonly int $sessionId,
        private readonly int $clientId,
        private ClassBookingStatus $status,
        ?string $idempotencyKey,
        private readonly DateTimeImmutable $bookedAt,
        private ?DateTimeImmutable $cancelledAt = null,
    ) {
        if ($id <= 0 || $companyId <= 0 || $gymId <= 0 || $sessionId <= 0 || $clientId <= 0) {
            throw InvalidClassBooking::invalidIdentifiers();
        }

        $this->publicId = self::cleanPublicId($publicId);
        $this->idempotencyKey = self::cleanIdempotencyKey($idempotencyKey);

        if ($status === ClassBookingStatus::BOOKED && $cancelledAt !== null) {
            throw InvalidClassBooking::bookedCannotHaveCancelledAt();
        }
        if ($status === ClassBookingStatus::CANCELLED && $cancelledAt === null) {
            throw InvalidClassBooking::cancelledRequiresCancelledAt();
        }
        if ($cancelledAt !== null && $cancelledAt < $bookedAt) {
            throw InvalidClassBooking::cancelledBeforeBooked();
        }
    }

    public static function cleanIdempotencyKey(?string $idempotencyKey): ?string
    {
        if ($idempotencyKey === null) {
            return null;
        }

        $trimmed = trim($idempotencyKey);
        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::IDEMPOTENCY_KEY_MAX) {
            throw InvalidClassBooking::idempotencyKeyTooLong(self::IDEMPOTENCY_KEY_MAX);
        }

        return $trimmed;
    }

    private static function cleanPublicId(string $publicId): string
    {
        $trimmed = trim($publicId);
        if ($trimmed === '') {
            throw InvalidClassBooking::publicIdRequired();
        }

        return $trimmed;
    }

    public function cancel(DateTimeImmutable $at): void
    {
        if ($this->status === ClassBookingStatus::CANCELLED) {
            throw InvalidClassBooking::alreadyCancelled($this->id);
        }
        if ($at < $this->bookedAt) {
            throw InvalidClassBooking::cancelledBeforeBooked();
        }

        $this->status = ClassBookingStatus::CANCELLED;
        $this->cancelledAt = $at;
    }

    public function isBooked(): bool
    {
        return $this->status === ClassBookingStatus::BOOKED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ClassBookingStatus::CANCELLED;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function publicId(): string
    {
        return $this->publicId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function gymId(): int
    {
        return $this->gymId;
    }

    public function sessionId(): int
    {
        return $this->sessionId;
    }

    public function clientId(): int
    {
        return $this->clientId;
    }

    public function status(): ClassBookingStatus
    {
        return $this->status;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function bookedAt(): DateTimeImmutable
    {
        return $this->bookedAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }
}
