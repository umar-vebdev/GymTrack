<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain;

use App\Shared\Domain\Money;
use DomainException;

/**
 * Доменная сущность тарифного плана платформы GymTrack для залов (Бизнес-ТЗ 1.1; Тех. ТЗ 3.6).
 */
final class PlatformPlan
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly BillingPeriod $period,
        private readonly Money $price,
        private readonly int $maxGyms,
        private readonly int $maxStaff,
        private bool $isActive = true
    ) {
        if (trim($name) === '') {
            throw new DomainException('Название тарифа платформы не может быть пустым.');
        }

        if ($maxGyms < 1) {
            throw new DomainException('Лимит филиалов по тарифу должен быть не менее 1.');
        }

        if ($maxStaff < 1) {
            throw new DomainException('Лимит сотрудников по тарифу должен быть не менее 1.');
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function period(): BillingPeriod
    {
        return $this->period;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function maxGyms(): int
    {
        return $this->maxGyms;
    }

    public function maxStaff(): int
    {
        return $this->maxStaff;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}

