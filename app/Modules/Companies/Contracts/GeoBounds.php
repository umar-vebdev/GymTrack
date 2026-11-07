<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

use InvalidArgumentException;

/**
 * Прямоугольная видимая область карты: юго-западный и северо-восточный углы (Бизнес-ТЗ 7).
 * Области, пересекающие 180-й меридиан, не поддерживаются: долгота юго-запада не больше долготы северо-востока.
 */
final class GeoBounds
{
    public function __construct(
        public readonly float $swLat,
        public readonly float $swLng,
        public readonly float $neLat,
        public readonly float $neLng,
    ) {
        foreach ([$swLat, $neLat] as $lat) {
            if ($lat < -90.0 || $lat > 90.0) {
                throw new InvalidArgumentException('Latitude must be within [-90, 90].');
            }
        }
        foreach ([$swLng, $neLng] as $lng) {
            if ($lng < -180.0 || $lng > 180.0) {
                throw new InvalidArgumentException('Longitude must be within [-180, 180].');
            }
        }
        if ($swLat > $neLat || $swLng > $neLng) {
            throw new InvalidArgumentException('South-west corner must not exceed north-east corner.');
        }
    }
}
