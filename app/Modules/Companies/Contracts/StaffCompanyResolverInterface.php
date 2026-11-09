<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

/**
 * Публичный контракт модуля Companies: в какой компании работает пользователь.
 */
interface StaffCompanyResolverInterface
{
    /**
     * Возвращает ID компании сотрудника бэк-офиса (владелец или администратор; пока — первая найденная)
     * или null, если пользователь не сотрудник бэк-офиса. Тренер сюда не попадает (D-012).
     */
    public function companyIdForUser(int $userId): ?int;

    /**
     * Возвращает ID компании, в которой пользователь работает тренером, или null.
     */
    public function companyIdForTrainer(int $userId): ?int;

    /**
     * Является ли пользователь владельцем указанной компании.
     */
    public function isOwner(int $userId, int $companyId): bool;
}
