<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Contracts;

use DateTimeImmutable;

/**
 * Публичный контракт модуля Memberships: выдача абонемента клиенту (используется Sales).
 */
interface MembershipIssuerInterface
{
    /**
     * Выдаёт абонемент типа $typeId клиенту $clientId и возвращает ID абонемента.
     *
     * @param  int|null  $issuedByUserId  сотрудник, оформивший выдачу (для аудита)
     */
    public function issue(
        int $companyId,
        int $clientId,
        int $typeId,
        DateTimeImmutable $at,
        ?int $issuedByUserId = null
    ): int;
}
