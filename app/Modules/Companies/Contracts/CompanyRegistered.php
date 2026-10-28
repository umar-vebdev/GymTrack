<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/**
 * Доменное событие: зарегистрирована новая компания (с первым залом).
 * Публикуется в Contracts, чтобы модуль Billing автоматически запустил пробный период (Бизнес-ТЗ 4.1).
 */
final class CompanyRegistered implements DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly int $companyId,
        public readonly int $ownerId
    ) {
        $this->occurredOn = new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}

