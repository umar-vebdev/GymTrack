<?php

declare(strict_types=1);

namespace App\Modules\Companies\Domain;

final class StaffMember
{
    public function __construct(
        private readonly int $id,
        private readonly int $userId,
        private readonly int $companyId,
        private readonly Role $role
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function role(): Role
    {
        return $this->role;
    }
}
