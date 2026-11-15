<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Rules;

use App\Modules\Visits\Domain\Exceptions\CheckInDenied;

/**
 * Опциональная геопроверка при чекине (Бизнес-ТЗ 6.2, 11; D-019):
 * если в зале включена геопроверка и заданы координаты витрины, клиент должен находиться в пределах радиуса.
 */
final class WithinGymGeoFenceRule implements CheckInRuleInterface
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    public function validate(CheckInContext $ctx): void
    {
        if (!$ctx->geoCheckEnabled || $ctx->gymLatitude === null || $ctx->gymLongitude === null) {
            return;
        }

        if ($ctx->clientLatitude === null || $ctx->clientLongitude === null) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_GEO_LOCATION_REQUIRED);
        }

        $distanceMeters = self::distanceInMeters(
            $ctx->gymLatitude,
            $ctx->gymLongitude,
            $ctx->clientLatitude,
            $ctx->clientLongitude,
        );

        if ($distanceMeters > $ctx->geoCheckRadiusMeters) {
            throw CheckInDenied::forReason(CheckInDenied::REASON_OUTSIDE_GYM_GEOFENCE);
        }
    }

    private static function distanceInMeters(float $latFrom, float $lonFrom, float $latTo, float $lonTo): float
    {
        $latFromRad = deg2rad($latFrom);
        $lonFromRad = deg2rad($lonFrom);
        $latToRad = deg2rad($latTo);
        $lonToRad = deg2rad($lonTo);

        $latDelta = $latToRad - $latFromRad;
        $lonDelta = $lonToRad - $lonFromRad;

        $a = sin($latDelta / 2) ** 2
            + cos($latFromRad) * cos($latToRad) * (sin($lonDelta / 2) ** 2);

        return 2.0 * self::EARTH_RADIUS_METERS * asin(min(1.0, sqrt($a)));
    }
}
