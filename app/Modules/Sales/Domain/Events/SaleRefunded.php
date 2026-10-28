<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Events;

use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\Money;
use DateTimeImmutable;

/**
 * Аудируемое доменное событие: возврат/отмена продажи владельцем (Тех. ТЗ 9.5: SALE_REFUNDED).
 */
final class SaleRefunded implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly int $saleId,
        private readonly int $companyId,
        private readonly ?int $clientId,
        private readonly Money $amount,
        private readonly string $reason,
        private readonly int $refundedByUserId
    ) {
        $this->occurredOn = new DateTimeImmutable;
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function saleId(): int
    {
        return $this->saleId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function userId(): int
    {
        return $this->refundedByUserId;
    }

    public function action(): string
    {
        return 'SALE_REFUNDED';
    }

    public function entityType(): string
    {
        return 'Sale';
    }

    public function entityId(): string
    {
        return (string) $this->saleId;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'status' => 'refunded',
            'client_id' => $this->clientId,
            'amount_minor' => $this->amount->amount(),
            'currency' => $this->amount->currency(),
            'reason' => $this->reason,
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}

