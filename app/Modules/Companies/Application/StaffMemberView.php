<?php

declare(strict_types=1);

namespace App\Modules\Companies\Application;

use App\Modules\Companies\Domain\Role;

final class StaffMemberView
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $companyId,
        public readonly Role $role,
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly ?string $email,
    ) {}
}
