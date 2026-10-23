<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/**
 * Доменное событие: пользователь подтвердил номер телефона.
 * Лежит в Contracts, чтобы другие модули могли на него подписаться.
 */
final class PhoneVerified implements DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly int $userId,
        public readonly string $phone
    ) {
        $this->occurredOn = new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
