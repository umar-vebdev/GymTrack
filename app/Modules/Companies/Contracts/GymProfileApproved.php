<?php

declare(strict_types=1);

namespace App\Modules\Companies\Contracts;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;

/**
 * Доменное событие: витрина зала одобрена модератором платформы (Бизнес-ТЗ 4.2; Тех. ТЗ 3.1, 7.1).
 */
final class GymProfileApproved implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        public readonly int $gymId,
        public readonly int $companyId,
        public readonly int $moderatorUserId,
        ?DateTimeImmutable $occurredOn = null,
    ) {
        $this->occurredOn = $occurredOn ?? new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function userId(): int
    {
        return $this->moderatorUserId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function action(): string
    {
        return 'GYM_PROFILE_APPROVED';
    }

    public function entityType(): string
    {
        return 'GymProfile';
    }

    public function entityId(): string
    {
        return (string) $this->gymId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'company_id' => $this->companyId,
            'approved_at' => $this->occurredOn->format(DATE_ATOM),
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}
