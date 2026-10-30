<?php

declare(strict_types=1);

namespace App\Modules\Visits\Domain;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class Visit
{
    public const int CANCELLATION_WINDOW_SECONDS = 86400; // 24 часа (Бизнес-ТЗ 5.4)

    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly int $gymId,
        private readonly int $clientId,
        private readonly int $membershipId,
        private readonly VisitMethod $method,
        private VisitStatus $status,
        private readonly DateTimeImmutable $visitedAt,
        private readonly string $visitDate,
        private readonly ?int $registeredByUserId,
        private readonly ?string $device = null,
        private readonly ?string $idempotencyKey = null,
        private ?string $cancelReason = null,
        private ?int $cancelledByUserId = null,
        private ?DateTimeImmutable $cancelledAt = null,
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

    public function clientId(): int
    {
        return $this->clientId;
    }

    public function membershipId(): int
    {
        return $this->membershipId;
    }

    public function method(): VisitMethod
    {
        return $this->method;
    }

    public function status(): VisitStatus
    {
        return $this->status;
    }

    public function visitedAt(): DateTimeImmutable
    {
        return $this->visitedAt;
    }

    public function visitDate(): string
    {
        return $this->visitDate;
    }

    public function registeredByUserId(): ?int
    {
        return $this->registeredByUserId;
    }

    public function device(): ?string
    {
        return $this->device;
    }

    public function idempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function cancelReason(): ?string
    {
        return $this->cancelReason;
    }

    public function cancelledByUserId(): ?int
    {
        return $this->cancelledByUserId;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancel(string $reason, int $cancelledByUserId, DateTimeImmutable $at): void
    {
        if ($this->status === VisitStatus::Cancelled) {
            throw new DomainException('Отметка посещения уже отменена.');
        }

        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Причина отмены посещения обязательна.');
        }

        $elapsedSeconds = $at->getTimestamp() - $this->visitedAt->getTimestamp();
        if ($elapsedSeconds > self::CANCELLATION_WINDOW_SECONDS) {
            throw new DomainException('Отменить ошибочную отметку посещения можно только в течение 24 часов.');
        }

        $this->status = VisitStatus::Cancelled;
        $this->cancelReason = $trimmed;
        $this->cancelledByUserId = $cancelledByUserId;
        $this->cancelledAt = $at;
    }
}

