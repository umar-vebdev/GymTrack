<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/**
 * Краткая сводка пользователя для отображения в других модулях (например, список сотрудников Companies).
 */
final class UserSummary
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly ?string $email,
    ) {}
}

