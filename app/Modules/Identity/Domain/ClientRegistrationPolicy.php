<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Exceptions\ClientRegistrationDenied;
use DateTimeImmutable;

final class ClientRegistrationPolicy
{
    public const int MINIMUM_AGE_YEARS = 18;

    /**
     * @throws ClientRegistrationDenied
     */
    public static function assertEligible(
        DateTimeImmutable $birthDate,
        bool $consented,
        DateTimeImmutable $at,
    ): void {
        if (!$consented) {
            throw ClientRegistrationDenied::consentRequired();
        }

        $cutoffDate = $at->setTime(0, 0, 0)->modify('-'.self::MINIMUM_AGE_YEARS.' years');

        if ($birthDate->setTime(0, 0, 0) > $cutoffDate) {
            throw ClientRegistrationDenied::underage();
        }
    }
}
