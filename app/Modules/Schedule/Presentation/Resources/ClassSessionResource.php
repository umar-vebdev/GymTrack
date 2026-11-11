<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation\Resources;

use App\Modules\Schedule\Domain\ClassSession;
use DateTimeInterface;

final class ClassSessionResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ClassSession $session): array
    {
        return [
            'id' => $session->id(),
            'public_id' => $session->publicId(),
            'gym_id' => $session->gymId(),
            'template_id' => $session->templateId(),
            'trainer_id' => $session->trainerId(),
            'title' => $session->title(),
            'capacity' => $session->capacity(),
            'booked_count' => $session->bookedCount(),
            'available_seats' => $session->availableSeats(),
            'starts_at' => $session->startsAt()->format(DateTimeInterface::ATOM),
            'ends_at' => $session->endsAt()->format(DateTimeInterface::ATOM),
            'status' => $session->status()->value,
        ];
    }
}
