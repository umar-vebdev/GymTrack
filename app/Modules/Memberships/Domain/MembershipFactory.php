<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

use DateTimeImmutable;

/**
 * Factory (фабрика): создаёт начальное состояние абонемента из типа и момента выдачи.
 */
final class MembershipFactory
{
    public static function termsFor(MembershipType $type, DateTimeImmutable $issuedAt): IssuedTerms
    {
        $rules = $type->rules();
        $remainingVisits = $rules->kind === MembershipKind::Visits ? $rules->visitsCount : null;

        if ($rules->startMode === StartMode::FirstVisit) {
            return new IssuedTerms(MembershipStatus::Pending, $remainingVisits, null, null);
        }

        return new IssuedTerms(
            MembershipStatus::Active,
            $remainingVisits,
            $issuedAt,
            $rules->endsAtFor($issuedAt)
        );
    }
}
