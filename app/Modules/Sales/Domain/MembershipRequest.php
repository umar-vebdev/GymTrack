<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use DateTimeImmutable;
use DomainException;

final class MembershipRequest
{
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly int $gymId,
        private readonly int $userId,
        private ?int $clientId,
        private readonly int $membershipTypeId,
        private MembershipRequestStatus $status,
        private readonly DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $paidAt = null,
        private ?int $saleId = null,
        private ?int $membershipId = null,
        private ?string $idempotencyKey = null,
        private ?DateTimeImmutable $createdAt = null
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function gymId(): int
    {
        return $this->gymId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function clientId(): ?int
    {
        return $this->clientId;
    }

    public function membershipTypeId(): int
    {
        return $this->membershipTypeId;
    }

    public function status(): MembershipRequestStatus
    {
        return $this->status;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function paidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function saleId(): ?int
    {
        return $this->saleId;
    }

    public function membershipId(): ?int
    {
        return $this->membershipId;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->status === MembershipRequestStatus::EXPIRED
            || ($this->status === MembershipRequestStatus::PENDING && $now >= $this->expiresAt);
    }

    public function canBePaid(DateTimeImmutable $now): bool
    {
        return $this->status === MembershipRequestStatus::PENDING && $now < $this->expiresAt;
    }

    public function markPaid(int $saleId, int $membershipId, DateTimeImmutable $paidAt, ?int $clientId = null): void
    {
        if ($this->status !== MembershipRequestStatus::PENDING) {
            throw new DomainException("Невозможно оплатить заявку в статусе {$this->status->value}.");
        }

        $this->status = MembershipRequestStatus::PAID;
        $this->saleId = $saleId;
        $this->membershipId = $membershipId;
        $this->paidAt = $paidAt;
        if ($clientId !== null) {
            $this->clientId = $clientId;
        }
    }

    public function cancel(): void
    {
        if ($this->status !== MembershipRequestStatus::PENDING) {
            throw new DomainException("Невозможно отменить заявку в статусе {$this->status->value}.");
        }

        $this->status = MembershipRequestStatus::CANCELLED;
    }

    public function expire(): void
    {
        if ($this->status !== MembershipRequestStatus::PENDING) {
            throw new DomainException("Невозможно перевести в статус просроченной заявку в статусе {$this->status->value}.");
        }

        $this->status = MembershipRequestStatus::EXPIRED;
    }

    public function linkClient(int $clientId): void
    {
        $this->clientId = $clientId;
    }
}
