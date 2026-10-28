<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain\Events;

use App\Modules\Sales\Domain\PaymentMethod;
use App\Shared\Domain\AuditableEvent;
use App\Shared\Domain\DomainEvent;
use App\Shared\Domain\Money;
use DateTimeImmutable;

final class PaymentMarked implements AuditableEvent, DomainEvent
{
    private readonly DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly int $saleId,
        private readonly int $companyId,
        private readonly ?int $clientId,
        private readonly PaymentMethod $method,
        private readonly Money $amount,
        private readonly int $acceptedByUserId
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

    public function userId(): int
    {
        return $this->acceptedByUserId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function action(): string
    {
        return 'PAYMENT_MARKED';
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
            'client_id' => $this->clientId,
            'payment_method' => $this->method->value,
            'amount_minor' => $this->amount->amount(),
            'currency' => $this->amount->currency(),
        ];
    }

    public function ipAddress(): ?string
    {
        return null;
    }
}
