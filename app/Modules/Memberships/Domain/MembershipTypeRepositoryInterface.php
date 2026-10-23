<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

use App\Shared\Domain\Money;

interface MembershipTypeRepositoryInterface
{
    public function findById(int $id): ?MembershipType;

    /**
     * Создаёт активный тип абонемента и возвращает его ID.
     */
    public function create(int $companyId, string $name, Money $price, MembershipRules $rules): int;
}
