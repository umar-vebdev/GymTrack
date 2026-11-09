<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Domain;

use App\Modules\Schedule\Domain\Exceptions\InvalidClassTemplate;

/**
 * Шаблон еженедельного группового занятия в расписании зала (Бизнес-ТЗ 8; D-011).
 * День недели: 1 (понедельник) .. 7 (воскресенье) по ISO-8601.
 * Время начала: минуты от полуночи (0..1439).
 */
final class ClassTemplate
{
    public const TITLE_MAX = 100;

    public const DESCRIPTION_MAX = 1000;

    public const MIN_DURATION_MINUTES = 15;

    public const MAX_DURATION_MINUTES = 240;

    public const MIN_CAPACITY = 1;

    public const MAX_CAPACITY = 200;

    private string $title;

    private ?string $description;

    private int $trainerId;

    private int $weekday;

    private int $startTimeMinute;

    private int $durationMinutes;

    private int $capacity;

    public function __construct(
        private readonly int $id,
        private readonly string $publicId,
        private readonly int $companyId,
        private readonly int $gymId,
        int $trainerId,
        string $title,
        ?string $description,
        int $weekday,
        int $startTimeMinute,
        int $durationMinutes,
        int $capacity,
        private bool $isActive,
    ) {
        $this->trainerId = $trainerId;
        $this->title = self::cleanTitle($title);
        $this->description = self::cleanDescription($description);
        $this->weekday = self::guardWeekday($weekday);
        $this->startTimeMinute = self::guardStartTimeMinute($startTimeMinute);
        $this->durationMinutes = self::guardDurationMinutes($durationMinutes);
        $this->capacity = self::guardCapacity($capacity);
    }

    public static function cleanTitle(string $title): string
    {
        $trimmed = trim($title);

        if ($trimmed === '') {
            throw InvalidClassTemplate::titleRequired();
        }
        if (mb_strlen($trimmed) > self::TITLE_MAX) {
            throw InvalidClassTemplate::titleTooLong(self::TITLE_MAX);
        }

        return $trimmed;
    }

    public static function cleanDescription(?string $description): ?string
    {
        $trimmed = $description !== null ? trim($description) : null;

        if ($trimmed === null || $trimmed === '') {
            return null;
        }
        if (mb_strlen($trimmed) > self::DESCRIPTION_MAX) {
            throw InvalidClassTemplate::descriptionTooLong(self::DESCRIPTION_MAX);
        }

        return $trimmed;
    }

    public static function guardWeekday(int $weekday): int
    {
        if ($weekday < 1 || $weekday > 7) {
            throw InvalidClassTemplate::invalidWeekday($weekday);
        }

        return $weekday;
    }

    public static function guardStartTimeMinute(int $minute): int
    {
        if ($minute < 0 || $minute > 1439) {
            throw InvalidClassTemplate::invalidStartTimeMinute($minute);
        }

        return $minute;
    }

    public static function guardDurationMinutes(int $durationMinutes): int
    {
        if ($durationMinutes < self::MIN_DURATION_MINUTES || $durationMinutes > self::MAX_DURATION_MINUTES) {
            throw InvalidClassTemplate::invalidDurationMinutes(self::MIN_DURATION_MINUTES, self::MAX_DURATION_MINUTES);
        }

        return $durationMinutes;
    }

    public static function guardCapacity(int $capacity): int
    {
        if ($capacity < self::MIN_CAPACITY || $capacity > self::MAX_CAPACITY) {
            throw InvalidClassTemplate::invalidCapacity(self::MIN_CAPACITY, self::MAX_CAPACITY);
        }

        return $capacity;
    }

    public function update(
        int $trainerId,
        string $title,
        ?string $description,
        int $weekday,
        int $startTimeMinute,
        int $durationMinutes,
        int $capacity,
    ): void {
        $cleanTitle = self::cleanTitle($title);
        $cleanDescription = self::cleanDescription($description);
        $validWeekday = self::guardWeekday($weekday);
        $validStart = self::guardStartTimeMinute($startTimeMinute);
        $validDuration = self::guardDurationMinutes($durationMinutes);
        $validCapacity = self::guardCapacity($capacity);

        $this->trainerId = $trainerId;
        $this->title = $cleanTitle;
        $this->description = $cleanDescription;
        $this->weekday = $validWeekday;
        $this->startTimeMinute = $validStart;
        $this->durationMinutes = $validDuration;
        $this->capacity = $validCapacity;
    }

    public function activate(): void
    {
        $this->isActive = true;
    }

    public function deactivate(): void
    {
        $this->isActive = false;
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

    public function trainerId(): int
    {
        return $this->trainerId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function weekday(): int
    {
        return $this->weekday;
    }

    public function startTimeMinute(): int
    {
        return $this->startTimeMinute;
    }

    public function durationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
