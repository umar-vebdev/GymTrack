<?php

declare(strict_types=1);

namespace App\Modules\Companies\Presentation\Resources;

use App\Modules\Companies\Domain\GymProfile;

final class GymProfileResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(GymProfile $profile): array
    {
        return [
            'gym_id' => $profile->gymId(),
            'description' => $profile->description(),
            'address' => $profile->address(),
            'district' => $profile->district(),
            'is_public' => $profile->isPublic(),
            'latitude' => $profile->latitude(),
            'longitude' => $profile->longitude(),
            'phone' => $profile->phone(),
            'working_hours' => $profile->workingHours(),
            'amenities' => $profile->amenities(),
            'moderation_status' => $profile->status()->value,
            'rejection_reason' => $profile->rejectionReason(),
            'published_at' => $profile->publishedAt()?->format(DATE_ATOM),
        ];
    }
}
