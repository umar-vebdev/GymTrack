<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

enum Role: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case TRAINER = 'trainer';

    /**
     * Роли бэк-офиса: имеют доступ к кабинету зала. Тренер к ним не относится (D-012).
     *
     * @return list<self>
     */
    public static function backOffice(): array
    {
        return [self::OWNER, self::ADMIN];
    }
}
