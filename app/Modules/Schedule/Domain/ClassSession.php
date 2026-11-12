<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain;

use App\Modules\Schedule\Domain\Exceptions\InvalidClassSession;
use DateTimeImmutable;

/**
 * Конкретное групповое занятие расписания на дату и время (Бизнес-ТЗ 8; D-011).
 * Время начала и окончания хранится в UTC.
 */
final class ClassSession
{
    private string $title;

    private int $capacity;

    private int $bookedCount;

    public function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly int $companyId,
        private readonly int $gymId,
        private readonly int $templateId,
        private readonly int $trainerId,
        string $title,
        int $capacity,
        int $bookedCount,
        private readonly DateTimeImmutable $startsAt,
        private readonly DateTimeImmutable $endsAt,
        private ClassSessionStatus $status,
    ) {
        $this->title = ClassTemplate::cleanTitle($title);
        $this->capacity = ClassTemplate::guardCapacity($capacity);

        if ($startsAt >= $endsAt) {
            throw InvalidClassSession::invalidTimeRange();
        }
        if ($bookedCount < 0 || $bookedCount > $this->capacity) {
            throw InvalidClassSession::invalidBookedCount($bookedCount, $this->capacity);
        }

        $this->bookedCount = $bookedCount;
    }

    public function cancel(): void
    {
        $this->status = ClassSessionStatus::CANCELLED;
    }

    public function assertOpenForBooking(DateTimeImmutable $at): void
    {
        if ($this->status !== ClassSessionStatus::SCHEDULED) {
            throw InvalidClassSession::cancelled($this->id);
        }
        if ($this->startsAt <= $at) {
            throw InvalidClassSession::alreadyStarted($this->id);
        }
    }

    public function reserveSeat(?DateTimeImmutable $at = null): void
    {
        if ($at !== null) {
            $this->assertOpenForBooking($at);
        } elseif ($this->status !== ClassSessionStatus::SCHEDULED) {
            throw InvalidClassSession::cancelled($this->id);
        }

        if ($this->bookedCount >= $this->capacity) {
            throw InvalidClassSession::noSeatsLeft($this->id);
        }

        $this->bookedCount++;
    }

    public function releaseSeat(): void
    {
        if ($this->bookedCount > 0) {
            $this->bookedCount--;
        }
    }

    public function availableSeats(): int
    {
        return max(0, $this->capacity - $this->bookedCount);
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

    public function templateId(): int
    {
        return $this->templateId;
    }

    public function trainerId(): int
    {
        return $this->trainerId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function bookedCount(): int
    {
        return $this->bookedCount;
    }

    public function startsAt(): DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function status(): ClassSessionStatus
    {
        return $this->status;
    }
}
