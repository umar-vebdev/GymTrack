<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

use DateTimeZone;
use InvalidArgumentException;

final class Gym
{
    public const DEFAULT_TIMEZONE = 'Asia/Dushanbe';

    public const DEFAULT_GEO_RADIUS_METERS = 200;

    public const MIN_GEO_RADIUS_METERS = 50;

    public const MAX_GEO_RADIUS_METERS = 5000;

    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly string $name,
        private readonly ?string $address,
        private readonly string $timezone = self::DEFAULT_TIMEZONE,
        private readonly bool $geoCheckEnabled = false,
        private readonly int $geoCheckRadiusMeters = self::DEFAULT_GEO_RADIUS_METERS,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Название зала не может быть пустым.');
        }

        if (!self::isValidTimezone($timezone)) {
            throw new InvalidArgumentException("Некорректный часовой пояс зала: {$timezone}");
        }

        if ($geoCheckRadiusMeters < self::MIN_GEO_RADIUS_METERS || $geoCheckRadiusMeters > self::MAX_GEO_RADIUS_METERS) {
            throw new InvalidArgumentException(
                sprintf(
                    'Радиус геопроверки должен быть от %d до %d метров.',
                    self::MIN_GEO_RADIUS_METERS,
                    self::MAX_GEO_RADIUS_METERS,
                )
            );
        }
    }

    public function withDetails(
        string $name,
        ?string $address,
        string $timezone,
        ?bool $geoCheckEnabled = null,
        ?int $geoCheckRadiusMeters = null,
    ): self {
        return new self(
            id: $this->id,
            companyId: $this->companyId,
            name: trim($name),
            address: $address !== null ? (trim($address) !== '' ? trim($address) : null) : null,
            timezone: $timezone,
            geoCheckEnabled: $geoCheckEnabled ?? $this->geoCheckEnabled,
            geoCheckRadiusMeters: $geoCheckRadiusMeters ?? $this->geoCheckRadiusMeters,
        );
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

    public function geoCheckEnabled(): bool
    {
        return $this->geoCheckEnabled;
    }

    public function geoCheckRadiusMeters(): int
    {
        return $this->geoCheckRadiusMeters;
    }
}
