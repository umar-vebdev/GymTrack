<?php

declare(strict_types=1);

namespace App\Modules\Clients\Contracts;

/**
 * Публичный контракт модуля Clients: узкий интерфейс только для проверок существования и привязки.
 */
interface ClientLookupInterface
{
    /**
     * Есть ли карточка клиента с таким ID в указанной компании.
     */
    public function existsInCompany(int $clientId, int $companyId): bool;

    /**
     * ID карточки клиента, привязанной к аккаунту пользователя, в указанной компании; null, если связи нет.
     */
    public function findIdByUser(int $userId, int $companyId): ?int;

    /**
     * Находит ID карточки клиента для пользователя или создаёт новую карточку в CRM зала.
     */
    public function findOrCreateForUser(int $userId, int $companyId): int;
}
