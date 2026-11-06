<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Resources;

use App\Modules\Companies\Contracts\PublicGymProfile;

final class PublicGymResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(PublicGymProfile $profile): array
    {
        return [
            'id' => $profile->publicId,
            'name' => $profile->name,
            'description' => $profile->description,
            'address' => $profile->address,
            'district' => $profile->district,
            'latitude' => $profile->latitude,
            'longitude' => $profile->longitude,
            'phone' => $profile->phone,
            'working_hours' => $profile->workingHours,
            'amenities' => $profile->amenities,
            'photos' => $profile->photos,
        ];
    }
}

