<?php

declare(strict_types=1);

namespace App\Modules\Schedule\Presentation\Resources;

use App\Modules\Schedule\Domain\ClassBooking;
use DateTimeInterface;

/**
 * Сериализация записи клиента на групповое занятие для JSON-ответов API.
 */
final class ClassBookingResource
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(ClassBooking $booking): array
    {
        return [
            'id' => $booking->id(),
            'public_id' => $booking->publicId(),
            'company_id' => $booking->companyId(),
            'gym_id' => $booking->gymId(),
            'session_id' => $booking->sessionId(),
            'client_id' => $booking->clientId(),
            'membership_id' => $booking->membershipId(),
            'status' => $booking->status()->value,
            'booked_at' => $booking->bookedAt()->format(DateTimeInterface::ATOM),
            'cancelled_at' => $booking->cancelledAt()?->format(DateTimeInterface::ATOM),
        ];
    }
}
