<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

/**
 * Публичный контракт модуля Memberships: отмена выданного абонемента (используется Sales при возврате продажи).
 */
interface MembershipCancellerInterface
{
    public function cancel(
        int $companyId,
        int $membershipId,
        string $reason,
        ?int $cancelledByUserId = null
    ): void;
}
