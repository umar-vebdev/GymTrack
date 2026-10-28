<?php

declare(strict_types=1);

namespace App\Modules\Sales\Domain;

use App\Shared\Domain\Money;
use DateTimeImmutable;
use DomainException;

/**
 * Доменная сущность продажи на кассе, содержащая позиции и отметку об оплате (PaymentMark).
 */
final class Sale
{
    /**
     * @param  list<SaleItem>  $items
     */
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly ?int $clientId,
        private SaleStatus $status,
        private readonly PaymentMark $paymentMark,
        private readonly array $items,
        private ?string $refundReason = null,
        private ?int $refundedByUserId = null,
        private ?DateTimeImmutable $refundedAt = null
    ) {
        if ($items === []) {
            throw new DomainException('Продажа должна содержать хотя бы одну позицию.');
        }

        $currency = $paymentMark->amount->currency();
        $total = new Money(0, $currency);

        foreach ($items as $item) {
            if ($item->isMembership() && $clientId === null) {
                throw new DomainException('Для продажи абонемента необходимо указать клиента.');
            }

            if ($item->unitPrice->currency() !== $currency) {
                throw new DomainException('Валюта позиции не совпадает с валютой отметки об оплате.');
            }

            $total = $total->add($item->lineTotal());
        }

        if ($total->amount() !== $paymentMark->amount->amount()) {
            throw new DomainException(sprintf(
                'Сумма отметки об оплате (%d) не совпадает с итоговой суммой позиций (%d).',
                $paymentMark->amount->amount(),
                $total->amount()
            ));
        }
    }

    public function id(): int
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function clientId(): ?int
    {
        return $this->clientId;
    }

    public function status(): SaleStatus
    {
        return $this->status;
    }

    public function paymentMark(): PaymentMark
    {
        return $this->paymentMark;
    }

    /**
     * @return list<SaleItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function refundReason(): ?string
    {
        return $this->refundReason;
    }

    public function refundedByUserId(): ?int
    {
        return $this->refundedByUserId;
    }

    public function refundedAt(): ?DateTimeImmutable
    {
        return $this->refundedAt;
    }

    public function refund(string $reason, int $byUserId, DateTimeImmutable $at): void
    {
        if ($this->status === SaleStatus::Refunded) {
            throw new DomainException('Продажа уже возвращена.');
        }

        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw new DomainException('Причина возврата обязательна.');
        }

        $this->status = SaleStatus::Refunded;
        $this->refundReason = $trimmed;
        $this->refundedByUserId = $byUserId;
        $this->refundedAt = $at;
    }
}
