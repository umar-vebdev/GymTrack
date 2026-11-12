<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Presentation\Resources;

use App\Modules\Schedule\Contracts\PublicClassSession;
use DateTimeInterface;

final class PublicClassSessionResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(PublicClassSession $session): array
    {
        return [
            'id' => $session->publicId,
            'title' => $session->title,
            'starts_at' => $session->startsAt->format(DateTimeInterface::ATOM),
            'ends_at' => $session->endsAt->format(DateTimeInterface::ATOM),
            'capacity' => $session->capacity,
            'available_seats' => $session->availableSeats,
            'trainer' => $session->trainerPublicId !== null && $session->trainerName !== null
                ? [
                    'id' => $session->trainerPublicId,
                    'name' => $session->trainerName,
                ]
                : null,
        ];
    }
}
