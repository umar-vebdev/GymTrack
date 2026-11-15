<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Resources;

use App\Modules\Companies\Domain\Gym;

final class GymResource
{
    /**
     * @return array{id: int, company_id: int, name: string, address: string|null, timezone: string, geo_check_enabled: bool, geo_check_radius_meters: int}
     */
    public static function toArray(Gym $gym): array
    {
        return [
            'id' => $gym->id(),
            'company_id' => $gym->companyId(),
            'name' => $gym->name(),
            'address' => $gym->address(),
            'timezone' => $gym->timezone(),
            'geo_check_enabled' => $gym->geoCheckEnabled(),
            'geo_check_radius_meters' => $gym->geoCheckRadiusMeters(),
        ];
    }
}
