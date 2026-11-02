<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain;

enum NotificationType: string
{
    case VisitRegistered = 'visit_registered';
    case MembershipRequestPaid = 'membership_request_paid';
    case MembershipExpiring7Days = 'membership_expiring_7_days';
    case MembershipExpiring1Day = 'membership_expiring_1_day';
}
