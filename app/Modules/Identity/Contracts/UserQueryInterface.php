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
}
