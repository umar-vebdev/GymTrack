<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

use DateTimeImmutable;

/**
 * Публичный контракт модуля Memberships: узкий интерфейс только для чтения (используется Visits).
 */
interface MembershipLookupInterface
{
    /**
     * Абонемент клиента, который будет использован при отметке: из подходящих берётся тот,
     * что заканчивается раньше (Бизнес-ТЗ 4.3). Null — подходящего абонемента нет.
     */
    public function findUsableForClient(int $companyId, int $clientId, DateTimeImmutable $at): ?MembershipSummary;
}
