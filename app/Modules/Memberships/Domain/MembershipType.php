<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

use App\Shared\Domain\Money;

/**
 * Тип абонемента — шаблон (название, цена, правила), из которого выдаются абонементы клиентам.
 */
final class MembershipType
{
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly string $name,
        private readonly Money $price,
        private readonly MembershipRules $rules,
        private readonly bool $isActive
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function rules(): MembershipRules
    {
        return $this->rules;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }
}
