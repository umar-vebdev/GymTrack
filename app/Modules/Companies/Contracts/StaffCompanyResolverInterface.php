<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * Публичный контракт модуля Companies: в какой компании работает пользователь.
 */
interface StaffCompanyResolverInterface
{
    /**
     * Возвращает ID компании сотрудника (пока — первая найденная) или null, если пользователь не сотрудник.
     */
    public function companyIdForUser(int $userId): ?int;

    /**
     * Является ли пользователь владельцем указанной компании.
     */
    public function isOwner(int $userId, int $companyId): bool;
}
