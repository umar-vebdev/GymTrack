<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

use InvalidArgumentException;

/**
 * Поиск «рядом со мной»: центр и радиус в километрах (Бизнес-ТЗ 7). Радиус в диапазоне (0, 100].
 */
final class GeoRadius
{
    public const MAX_RADIUS_KM = 100.0;

    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
        public readonly float $radiusKm,
    ) {
        if ($lat < -90.0 || $lat > 90.0) {
            throw new InvalidArgumentException('Latitude must be within [-90, 90].');
        }
        if ($lng < -180.0 || $lng > 180.0) {
            throw new InvalidArgumentException('Longitude must be within [-180, 180].');
        }
        if ($radiusKm <= 0.0 || $radiusKm > self::MAX_RADIUS_KM) {
            throw new InvalidArgumentException('Radius must be within (0, 100] km.');
        }
    }
}
