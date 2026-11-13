<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/**
 * Публичный контракт Identity: узкий интерфейс только для чтения пользователей.
 */
interface UserQueryInterface
{
    /**
     * ID пользователя с таким ПОДТВЕРЖДЁННЫМ телефоном или null.
     */
    public function findVerifiedUserIdByPhone(string $phone): ?int;

    /**
     * Краткая сводка пользователя по ID или null.
     */
    public function findSummaryById(int $userId): ?UserSummary;

    /**
     * Пакетная выборка сводок пользователей по списку ID (ключ — user_id).
     *
     * @param  list<int>  $userIds
     * @return array<int, UserSummary>
     */
    public function findSummariesByIds(array $userIds): array;
}
