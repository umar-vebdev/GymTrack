<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Resources;

use App\Modules\Catalog\Application\PublicGymCardResult;
use App\Modules\Memberships\Contracts\PublicMembershipType;

final class PublicGymCardResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(PublicGymCardResult $card): array
    {
        return array_merge(PublicGymResource::toArray($card->gym), [
            'membership_types' => array_map(
                static fn (PublicMembershipType $type): array => [
                    'id' => $type->publicId,
                    'name' => $type->name,
                    'kind' => $type->kind,
                    'start_mode' => $type->startMode,
                    'price_minor' => $type->priceMinor,
                    'currency' => $type->currency,
                    'visits_count' => $type->visitsCount,
                    'duration_days' => $type->durationDays,
                    'validity_days' => $type->validityDays,
                    'daily_limit' => $type->dailyLimit,
                    'time_from_minute' => $type->timeFromMinute,
                    'time_to_minute' => $type->timeToMinute,
                    'freeze_max_days' => $type->freezeMaxDays,
                    'freeze_max_times' => $type->freezeMaxTimes,
                ],
                $card->membershipTypes,
            ),
        ]);
    }
}
