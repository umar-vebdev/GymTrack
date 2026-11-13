<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

use DateTimeZone;
use InvalidArgumentException;

final class Gym
{
    public const DEFAULT_TIMEZONE = 'Asia/Dushanbe';

    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly string $name,
        private readonly ?string $address,
        private readonly string $timezone = self::DEFAULT_TIMEZONE,
    ) {
        if (!self::isValidTimezone($timezone)) {
            throw new InvalidArgumentException("Некорректный часовой пояс зала: {$timezone}");
        }
    }

    public static function isValidTimezone(string $timezone): bool
    {
        return $timezone !== '' && in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }
}
