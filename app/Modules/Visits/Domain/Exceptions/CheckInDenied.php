<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain\Exceptions;

use DomainException;

/**
 * Исключение, бросаемое, когда одно из правил проверки чек‑ина не прошло.
 *
 * Хранит машиночитаемый код причины ($reason), чтобы контроллер мог вернуть
 * корректный HTTP‑код и понятное сообщение ошибки.
 */
final class CheckInDenied extends DomainException
{
    public const string REASON_NO_ACTIVE_MEMBERSHIP = 'no_active_membership';

    public const string REASON_FROZEN = 'membership_frozen';

    public const string REASON_OUT_OF_TIME_WINDOW = 'outside_allowed_time';

    public const string REASON_DAILY_LIMIT_EXCEEDED = 'daily_limit_exceeded';

    private function __construct(
        public readonly string $reason,
    ) {
        parent::__construct('Check‑in denied: '.$reason);
    }

    public static function forReason(string $reason): self
    {
        return new self($reason);
    }
}
