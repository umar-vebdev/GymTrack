<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

enum MembershipStatus: string
{
    /** Ожидает активации (например, до первого посещения). */
    case Pending = 'pending';

    /** Активен и готов к использованию. */
    case Active = 'active';

    /** Заморожен. */
    case Frozen = 'frozen';

    /** Истёк по сроку или исчерпаны занятия. */
    case Expired = 'expired';

    /** Отменён (возврат/аннулирование администрацией). */
    case Cancelled = 'cancelled';

    public function isUsable(): bool
    {
        return $this === self::Active || $this === self::Pending;
    }
}
