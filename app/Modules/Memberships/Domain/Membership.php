<?php

declare(strict_types=1);

namespace App\Modules\Memberships\Domain;

use App\Modules\Memberships\Domain\Exceptions\MembershipNotUsable;
use App\Modules\Memberships\Domain\Exceptions\NoVisitsLeft;
use App\Modules\Memberships\Domain\Strategies\ConsumptionStrategy;
use App\Modules\Memberships\Domain\Strategies\ConsumptionStrategyResolver;
use DateTimeImmutable;
use DomainException;

/**
 * Богатая доменная сущность абонемента клиента (Rich Domain Model).
 * Инкапсулирует бизнес-правила жизненного цикла, списания и заморозки.
 */
final class Membership
{
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
        private readonly int $clientId,
        private readonly int $typeId,
        private readonly MembershipRules $rules,
        private MembershipStatus $status,
        private ?int $remainingVisits,
        private ?DateTimeImmutable $startsAt,
        private ?DateTimeImmutable $endsAt,
        private int $freezeDaysUsed = 0,
        private int $freezeTimesUsed = 0,
        private ?DateTimeImmutable $frozenAt = null
    ) {}

    public function id(): int
    {
        return $this->id;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function clientId(): int
    {
        return $this->clientId;
    }

    public function typeId(): int
    {
        return $this->typeId;
    }

    public function rules(): MembershipRules
    {
        return $this->rules;
    }

    public function status(): MembershipStatus
    {
        return $this->status;
    }

    public function remainingVisits(): ?int
    {
        return $this->remainingVisits;
    }

    public function startsAt(): ?DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function endsAt(): ?DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function freezeDaysUsed(): int
    {
        return $this->freezeDaysUsed;
    }

    public function freezeTimesUsed(): int
    {
        return $this->freezeTimesUsed;
    }

    public function frozenAt(): ?DateTimeImmutable
    {
        return $this->frozenAt;
    }

    /**
     * Списание посещения или фиксация визита с помощью стратегии списания.
     *
     * @throws MembershipNotUsable
     * @throws NoVisitsLeft
     */
    public function consumeVisit(DateTimeImmutable $at, ?ConsumptionStrategy $strategy = null): void
    {
        $this->expireIfDue($at);

        if (!$this->status->isUsable()) {
            throw MembershipNotUsable::forStatus($this->id, $this->status->value);
        }

        // Активация абонемента при первом визите, если был в ожидании
        if ($this->status === MembershipStatus::Pending) {
            $this->activateOnFirstVisit($at);
        }

        $strategy ??= ConsumptionStrategyResolver::resolve($this->rules->kind);
        $strategy->consume($this, $at);
    }

    public function decrementRemainingVisits(): void
    {
        if ($this->remainingVisits !== null && $this->remainingVisits > 0) {
            $this->remainingVisits--;

            if ($this->remainingVisits === 0) {
                $this->status = MembershipStatus::Expired;
            }
        }
    }

    /**
     * Проверка срока действия и автоматический перевод в статус Expired при необходимости.
     */
    public function expireIfDue(DateTimeImmutable $now): void
    {
        if ($this->status === MembershipStatus::Expired || $this->status === MembershipStatus::Cancelled) {
            return;
        }

        if ($this->endsAt !== null && $now > $this->endsAt) {
            $this->status = MembershipStatus::Expired;
        }
    }

    /**
     * Заморозка абонемента.
     */
    public function freeze(DateTimeImmutable $at): void
    {
        if ($this->status !== MembershipStatus::Active) {
            throw new DomainException(sprintf('Заморозить можно только активный абонемент (текущий статус: %s).', $this->status->value));
        }

        if ($this->rules->freezeMaxTimes <= 0 || $this->freezeTimesUsed >= $this->rules->freezeMaxTimes) {
            throw new DomainException('Лимит количества заморозок исчерпан.');
        }

        if ($this->freezeDaysUsed >= $this->rules->freezeMaxDays) {
            throw new DomainException('Лимит дней заморозки исчерпан.');
        }

        $this->status = MembershipStatus::Frozen;
        $this->frozenAt = $at;
        $this->freezeTimesUsed++;
    }

    /**
     * Разморозка абонемента с продлением даты окончания на срок фактически использованной заморозки.
     */
    public function unfreeze(DateTimeImmutable $now): void
    {
        if ($this->status !== MembershipStatus::Frozen || $this->frozenAt === null) {
            throw new DomainException('Абонемент не находится в состоянии заморозки.');
        }

        $daysFrozen = max(1, (int) ceil(($now->getTimestamp() - $this->frozenAt->getTimestamp()) / 86400));

        // Контроль общего лимита дней заморозки
        $remainingAllowedDays = $this->rules->freezeMaxDays - $this->freezeDaysUsed;
        $effectiveDays = min($daysFrozen, $remainingAllowedDays);

        $this->freezeDaysUsed += $effectiveDays;

        if ($this->endsAt !== null) {
            $this->endsAt = $this->endsAt->modify(sprintf('+%d days', $effectiveDays));
        }

        $this->status = MembershipStatus::Active;
        $this->frozenAt = null;
    }

    /**
     * Аннулирование абонемента владельцем.
     */
    public function cancel(): void
    {
        if ($this->status === MembershipStatus::Cancelled) {
            throw new DomainException('Абонемент уже отменён.');
        }

        $this->status = MembershipStatus::Cancelled;
    }

    private function activateOnFirstVisit(DateTimeImmutable $at): void
    {
        $this->status = MembershipStatus::Active;
        $this->startsAt = $at;

        if ($this->rules->durationDays !== null) {
            $this->endsAt = $at->modify(sprintf('+%d days', $this->rules->durationDays));
        } elseif ($this->rules->validityDays !== null) {
            $this->endsAt = $at->modify(sprintf('+%d days', $this->rules->validityDays));
        } elseif ($this->rules->kind === MembershipKind::Single) {
            $this->endsAt = $at->setTime(23, 59, 59);
        }
    }
}
